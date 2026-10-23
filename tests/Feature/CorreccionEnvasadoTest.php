<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\MovimientoInventario;
use App\Models\Permiso;
use App\Services\RegistrarEnvasado;
use App\Services\TokenOperacion;
use App\Services\VerificadorInventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class CorreccionEnvasadoTest extends TestCase
{
    use EnvasadoEscenario,RefreshDatabase;

    private function registrado(): array
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario']);
        $e['envasado'] = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 15], $e['usuario']);

        return $e;
    }

    private function datos(array $e, int $cantidad = 3, string $clase = 'error_administrativo', int $devolver = 3): array
    {
        return ['cantidad' => $cantidad, 'clasificacion' => $clase, 'motivo' => 'Corregir diferencia verificada físicamente', 'revertidas_previas' => $e['envasado']->fresh()->unidades_revertidas, 'devoluciones' => [$e['envasado']->consumos->first()->id => $devolver]];
    }

    public function test_error_administrativo_compensa_solo_materiales_declarados_y_audita_sin_editar_originales(): void
    {
        $e = $this->registrado();
        $original = MovimientoInventario::orderBy('id')->get()->toJson();
        $this->post(route('envasado.correcciones.previsualizar', $e['envasado']), $this->datos($e))->assertOk()->assertSee('Devolución declarada');
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->post(route('envasado.correcciones.store', $e['envasado']), $this->datos($e))->assertRedirect();
        $this->assertSame('88.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 12]);
        $this->assertSame($original, MovimientoInventario::orderBy('id')->limit(2)->get()->toJson());
        $this->assertDatabaseHas('auditoria', ['accion' => 'envasado_corregido']);
        $this->assertSame('error_administrativo', Auditoria::where('accion', 'envasado_corregido')->first()->detalle['clasificacion']);
        $this->assertTrue(app(VerificadorInventario::class)->revisar()['correcto']);
    }

    public function test_perdida_fisica_no_devuelve_materiales_consumidos(): void
    {
        $e = $this->registrado();
        $this->post(route('envasado.correcciones.store', $e['envasado']), $this->datos($e, 3, 'perdida_fisica', 0))->assertRedirect();
        $this->assertSame('85.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseHas('envasado_correccion_materiales', ['cantidad_devuelta' => 0, 'cantidad_perdida' => 3]);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 12]);
    }

    public function test_recuperacion_parcial_devuelve_solo_lo_expresamente_declarado(): void
    {
        $e = $this->registrado();
        $this->post(route('envasado.correcciones.store', $e['envasado']), $this->datos($e, 3, 'perdida_fisica', 1))->assertRedirect();
        $this->assertSame('86.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseHas('envasado_correccion_materiales', ['cantidad_devuelta' => 1, 'cantidad_perdida' => 2]);
    }

    public function test_sin_clasificacion_o_con_devolucion_excesiva_no_hay_cambios(): void
    {
        $e = $this->registrado();
        $d = $this->datos($e);
        unset($d['clasificacion']);
        $this->post(route('envasado.correcciones.store', $e['envasado']), $d)->assertSessionHasErrors('clasificacion');
        $d = $this->datos($e, 3, 'error_administrativo', 4);
        $this->post(route('envasado.correcciones.store', $e['envasado']), $d)->assertSessionHasErrors('devoluciones');
        $d = $this->datos($e);
        $d['devoluciones'] = [999 => 1];
        $this->post(route('envasado.correcciones.store', $e['envasado']), $d)->assertSessionHasErrors('devoluciones');
        $this->assertDatabaseCount('envasado_correcciones', 0);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_anulacion_total_no_puede_repetirse_y_conserva_historial(): void
    {
        $e = $this->registrado();
        $d = $this->datos($e, 15, 'error_administrativo', 15);
        $token = TokenOperacion::emitir($e['usuario']->id);
        $d['_token_operacion'] = $token;
        $this->post(route('envasado.correcciones.store', $e['envasado']), $d)->assertRedirect();
        $this->post(route('envasado.correcciones.store', $e['envasado']), $d)->assertRedirect();
        $this->assertDatabaseCount('envasado_correcciones', 1);
        $this->assertSame('anulado', $e['envasado']->fresh()->estado);
        unset($d['_token_operacion']);
        $this->post(route('envasado.correcciones.store', $e['envasado']), $d)->assertSessionHasErrors('cantidad');
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
    }

    public function test_varias_correcciones_parciales_no_chocan_con_origen_y_rechazan_formulario_obsoleto(): void
    {
        $e = $this->registrado();
        $viejo = $this->datos($e);
        $this->post(route('envasado.correcciones.store', $e['envasado']), $viejo)->assertRedirect();
        $this->post(route('envasado.correcciones.store', $e['envasado']), $viejo)->assertSessionHasErrors('cantidad');
        $this->post(route('envasado.correcciones.store', $e['envasado']), $this->datos($e))->assertRedirect();
        $this->assertDatabaseCount('envasado_correcciones', 2);
        $this->assertSame(6, $e['envasado']->fresh()->unidades_revertidas);
    }

    public function test_stock_fungible_permita_correccion_aunque_existan_despachos_posteriores(): void
    {
        $e = $this->registrado();
        $pedido = $this->pedidoEnvasado($e, 'listo_despacho');
        $this->post(route('cumplimiento.despachar', $pedido))->assertRedirect();
        $this->post(route('envasado.correcciones.store', $e['envasado']), $this->datos($e))->assertRedirect();
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 7]);
        $this->assertTrue(app(VerificadorInventario::class)->revisar()['correcto']);
    }

    public function test_stock_insuficiente_bloquea_anulacion_con_mensaje_basado_en_existencias(): void
    {
        $e = $this->registrado();
        $pedido = $this->pedidoEnvasado($e, 'listo_despacho');
        $this->post(route('cumplimiento.despachar', $pedido))->assertRedirect();
        $this->post(route('envasado.correcciones.store', $e['envasado']), $this->datos($e, 15, 'error_administrativo', 15))->assertSessionHasErrors('cantidad');
        $this->assertDatabaseCount('envasado_correcciones', 0);
        $this->assertSame('85.000', $e['insumo']->fresh()->cantidad_actual);
    }

    public function test_cambio_de_configuracion_no_altera_compensacion_historica(): void
    {
        $e = $this->registrado();
        $e['configuracion']->materiales()->update(['cantidad_por_unidad' => 5, 'estado' => false]);
        $e['insumo']->update(['estado' => false]);
        $this->post(route('envasado.correcciones.store', $e['envasado']), $this->datos($e))->assertRedirect();
        $this->assertSame('88.000', $e['insumo']->fresh()->cantidad_actual);
    }

    public function test_reportar_no_modifica_inventario_y_corregir_sin_permiso_da_403(): void
    {
        $e = $this->registrado();
        $e['usuario']->role->permisos()->detach(Permiso::where('nombre', 'envasado.corregir')->value('id'));
        $this->post(route('envasado.reportar', $e['envasado']), ['motivo' => 'Revisar la cantidad digitada'])->assertRedirect();
        $this->post(route('envasado.correcciones.store',$e['envasado']),$this->datos($e))->assertForbidden();
        $this->assertDatabaseCount('envasado_reportes',1);
        $this->assertDatabaseCount('envasado_correcciones',0);
        $this->assertDatabaseCount('movimientos_inventario',2);
        $this->get(route('envasado.show',$e['envasado']))->assertOk()->assertSee('Revisar la cantidad digitada');
    }
}
