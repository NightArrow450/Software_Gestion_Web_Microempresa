<?php

namespace Tests\Feature;

use App\Models\Envasado;
use App\Models\Inventario;
use App\Services\ActividadIndicadores;
use App\Services\CorregirEnvasado;
use App\Services\FiltrosIndicadores;
use App\Services\RegistrarEnvasado;
use App\Services\TokenOperacion;
use App\Services\VerificadorInventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class EnvasadoUnidadesTerminadasTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    private function escenarioHabitual(int $stock = 0): array
    {
        $e = $this->escenarioEnvasado(0, $stock);
        config(['envasado.control_materiales_exacto' => false]);

        return $e;
    }

    private function datos(array $e, int $cantidad = 32): array
    {
        return ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => $cantidad];
    }

    public function test_modo_predeterminado_no_exige_configuracion_ni_materiales_y_solo_ingresa_terminados(): void
    {
        $this->assertFalse(config('envasado.control_materiales_exacto'));
        $e = $this->escenarioHabitual(5);
        $e['configuracion']->materiales()->delete();
        $e['configuracion']->delete();
        $cuentas = [];
        foreach (['users', 'roles', 'permisos', 'rol_permiso'] as $tabla) {
            $cuentas[$tabla] = DB::table($tabla)->get()->toArray();
        }
        $this->actingAs($e['usuario'])->get(route('envasado.create'))->assertOk()->assertDontSee('name="configuracion_envasado_id"', false);
        $this->post(route('envasado.previsualizar'), $this->datos($e))->assertOk()->assertSee('No se registran consumos ni movimientos de insumos');
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);

        $this->post(route('envasado.store'), $this->datos($e))->assertSessionHasNoErrors()->assertRedirect();

        $envasado = Envasado::sole();
        $this->assertNull($envasado->configuracion_envasado_id);
        $this->assertNull($envasado->pedido_id);
        $auditoria = \App\Models\Auditoria::where('accion', 'envasado_registrado')->sole();
        $this->assertSame($e['usuario']->id, $auditoria->usuario_id);
        $this->assertFalse($auditoria->detalle['control_materiales_exacto']);
        $this->assertSame(32, $envasado->cantidad);
        $this->assertSame($e['usuario']->id, $envasado->usuario_id);
        $this->assertSame($e['presentacion']->codigo_sku, $envasado->presentacion_historica['sku']);
        $this->assertSame(37, (int) Inventario::sole()->cantidad_actual);
        $this->assertDatabaseCount('envasado_consumos', 0);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseHas('movimientos_inventario', ['origen_tipo' => 'envasado', 'tipo' => 'entrada', 'cantidad' => 32, 'insumo_id' => null]);
        $this->assertSame('0.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertTrue(app(VerificadorInventario::class)->revisar()['correcto']);
        foreach ($cuentas as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
    }

    public function test_doble_envio_y_anulacion_sin_materiales_no_duplican_movimientos(): void
    {
        $e = $this->escenarioHabitual();
        $datos = $this->datos($e) + ['_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $datos)->assertSessionHasNoErrors();
        $this->post(route('envasado.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('envasados', 1);
        $envasado = Envasado::sole();
        $correccion = ['clasificacion' => 'error_administrativo', 'cantidad' => 8, 'revertidas_previas' => 0, 'motivo' => 'Se registraron ocho unidades adicionales por error.', '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->post(route('envasado.correcciones.store', $envasado), $correccion)->assertSessionHasNoErrors();
        $this->post(route('envasado.correcciones.store', $envasado), $correccion)->assertSessionHasNoErrors();
        $this->assertSame(24, (int) Inventario::sole()->cantidad_actual);
        $this->assertDatabaseCount('envasado_correcciones', 1);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->post(route('envasado.correcciones.store', $envasado), array_replace($correccion, ['_token_operacion' => TokenOperacion::emitir($e['usuario']->id)]))->assertSessionHasErrors('cantidad');
        app(CorregirEnvasado::class)->ejecutar($envasado->fresh(), ['clasificacion' => 'perdida_fisica', 'cantidad' => 24, 'revertidas_previas' => 8, 'motivo' => 'Se registra la pérdida real de las unidades restantes.'], $e['usuario']);
        $this->assertSame(0, (int) Inventario::sole()->cantidad_actual);
        $this->assertSame('anulado', $envasado->fresh()->estado);
        $this->assertDatabaseCount('envasado_correccion_materiales', 0);
        $this->assertSame('0.000', $e['insumo']->fresh()->cantidad_actual);
    }

    public function test_el_formulario_no_puede_habilitar_consumos_ni_omitir_el_control_exacto(): void
    {
        $e = $this->escenarioHabitual();
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $this->datos($e) + ['control_materiales_exacto' => 1])->assertSessionHasErrors('control_materiales_exacto');
        config(['envasado.control_materiales_exacto' => true]);
        $this->post(route('envasado.store'), $this->datos($e) + ['control_materiales_exacto' => 0])->assertSessionHasErrors();
        $this->post(route('envasado.store'), $this->datos($e))->assertSessionHasErrors('configuracion_envasado_id');
        $this->assertDatabaseCount('envasados', 0);
    }

    public function test_no_devuelve_materiales_inventados_y_no_permite_stock_negativo(): void
    {
        $e = $this->escenarioHabitual();
        $envasado = app(RegistrarEnvasado::class)->ejecutar($this->datos($e, 2), $e['usuario']);
        $this->actingAs($e['usuario']);
        $datos = ['clasificacion' => 'perdida_fisica', 'cantidad' => 1, 'revertidas_previas' => 0, 'motivo' => 'Pérdida física confirmada de una unidad.'];
        $this->post(route('envasado.correcciones.store', $envasado), $datos + ['devoluciones' => [99 => 1]])->assertSessionHasErrors('devoluciones');
        Inventario::sole()->update(['cantidad_actual' => 0]);
        $this->post(route('envasado.correcciones.store', $envasado), $datos)->assertSessionHasErrors('cantidad');
        $this->assertDatabaseCount('envasado_correcciones', 0);
    }

    public function test_consumos_historicos_se_conservan_y_se_corrigen_con_sus_reglas_aunque_el_modo_actual_sea_simple(): void
    {
        $e = $this->escenarioEnvasado();
        $envasado = app(RegistrarEnvasado::class)->ejecutar($this->datos($e, 10) + ['configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0], $e['usuario']);
        $originales = DB::table('envasado_consumos')->get()->toArray();
        config(['envasado.control_materiales_exacto' => false]);
        $this->actingAs($e['usuario']);
        $datos = ['clasificacion' => 'error_administrativo', 'cantidad' => 1, 'revertidas_previas' => 0, 'motivo' => 'Corrección histórica sin recuperar los materiales.'];
        $this->post(route('envasado.correcciones.store', $envasado), $datos)->assertSessionHasErrors('devoluciones');
        $this->post(route('envasado.correcciones.store', $envasado), $datos + ['devoluciones' => [$envasado->consumos()->sole()->id => 0]])->assertSessionHasNoErrors();
        $this->assertEquals($originales, DB::table('envasado_consumos')->get()->toArray());
        $this->assertSame('90.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(9, (int) Inventario::sole()->cantidad_actual);
    }

    public function test_sin_control_numerico_no_se_generan_alertas_y_reportes_identifican_saldos_no_verificados(): void
    {
        $e = $this->escenarioHabitual();
        $this->actingAs($e['usuario'])->get(route('inventario.stock'))->assertOk()->assertViewHas('resumen', ['agotado' => 1, 'bajo' => 0, 'normal' => 0])->assertSee('Control numérico de materiales desactivado')->assertDontSee('Insumos, envases y materiales · mínimos');
        $filtros = FiltrosIndicadores::validar(['reporte' => 'inventario']);
        $filas = iterator_to_array(app(ActividadIndicadores::class)->inventario($filtros));
        $insumos = array_values(array_filter($filas, fn ($fila): bool => $fila['tipo_item'] === 'Insumo'));
        $this->assertSame('Saldo histórico sin verificación física', $insumos[0]['control_existencias']);
    }

    public function test_rechaza_cantidades_invalidas_sin_crear_operaciones(): void
    {
        $e = $this->escenarioHabitual();
        $this->actingAs($e['usuario']);
        foreach ([0, -1, '1.5', 1000001, 'texto'] as $cantidad) {
            $this->post(route('envasado.store'), ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => $cantidad])->assertSessionHasErrors('cantidad');
        }
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
