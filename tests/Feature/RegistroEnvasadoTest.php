<?php

namespace Tests\Feature;

use App\Models\Envasado;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MaterialEnvasado;
use App\Models\MovimientoInventario;
use App\Services\RegistrarEnvasado;
use App\Services\TokenOperacion;
use App\Services\VerificadorInventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class RegistroEnvasadoTest extends TestCase
{
    use EnvasadoEscenario,RefreshDatabase;

    private function datos(array $e, int $cantidad = 12): array
    {
        return ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => $e['configuracion']->version, 'cantidad' => $cantidad];
    }

    public function test_anticipado_descuenta_materiales_y_suma_producto_sin_duplicar_saldo_inicial(): void
    {
        $e = $this->escenarioEnvasado(100, 20);
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $this->datos($e))->assertRedirect();
        $envasado = Envasado::firstOrFail();
        $this->assertNull($envasado->pedido_id);
        $this->assertSame('88.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 32]);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertTrue(app(VerificadorInventario::class)->revisar()['correcto']);
        $this->get(route('envasado.show', $envasado))->assertOk()->assertSee('Stock disponible general: 32');
    }

    public function test_insumo_insuficiente_impide_toda_la_operacion(): void
    {
        $e = $this->escenarioEnvasado(5);
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $this->datos($e))->assertSessionHasErrors('materiales');
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame('5.000', $e['insumo']->fresh()->cantidad_actual);
    }

    public function test_fallo_tras_primer_descuento_revierte_todos_los_cambios(): void
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario']);
        $original = $e['insumo']->cantidad_actual;
        MovimientoInventario::creating(function ($m): void {
            if ($m->presentacion_producto_id !== null) {
                throw new \RuntimeException('Fallo de prueba al ingresar producto');
            }
        });
        try {
            app(RegistrarEnvasado::class)->ejecutar($this->datos($e), $e['usuario']);
            $this->fail('Debió fallar');
        } catch (\RuntimeException $ex) {
            $this->assertSame('Fallo de prueba al ingresar producto', $ex->getMessage());
        }
        $this->assertSame($original, $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_doble_envio_no_duplica_y_token_con_datos_distintos_se_rechaza(): void
    {
        $e = $this->escenarioEnvasado();
        $d = $this->datos($e) + ['_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $d)->assertRedirect();
        $this->post(route('envasado.store'), $d)->assertRedirect();
        $this->assertDatabaseCount('envasados', 1);
        $this->assertSame('88.000', $e['insumo']->fresh()->cantidad_actual);
        $d['cantidad'] = 13;
        $this->post(route('envasado.store'), $d)->assertSessionHasErrors('operacion');
    }

    public function test_envasados_parciales_mismo_pedido_no_cambian_estado_ni_reservan_stock(): void
    {
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e);
        $d = $this->datos($e, 2) + ['pedido_id' => $pedido->id];
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $d)->assertRedirect();
        $this->post(route('envasado.store'), $d)->assertRedirect();
        $this->assertDatabaseCount('envasados', 2);
        $this->assertSame('confirmado', $pedido->fresh()->estado);
        $this->assertSame('96.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(0, MovimientoInventario::whereNotNull('pedido_id')->count());
    }

    public function test_solo_confirmados_y_en_preparacion_admiten_vinculo(): void
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario']);
        foreach (['registrado', 'observado', 'listo_despacho', 'despachado', 'entregado', 'cancelado', 'confirmado', 'en_preparacion'] as $estado) {
            $pedido = $this->pedidoEnvasado($e, $estado);
            $r = $this->post(route('envasado.store'), $this->datos($e, 1) + ['pedido_id' => $pedido->id]);
            if (in_array($estado, ['confirmado', 'en_preparacion'], true)) {
                $r->assertSessionHasNoErrors();
            } else {
                $r->assertSessionHasErrors('pedido_id');
            }
            $this->assertSame($estado, $pedido->fresh()->estado);
        }
        $this->assertDatabaseCount('envasados', 2);
    }

    public function test_preview_y_consultas_no_generan_movimientos_y_exponen_demanda_separada(): void
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario'])->get(route('envasado.create'))->assertOk();
        $this->post(route('envasado.previsualizar'), $this->datos($e))->assertOk()->assertSee('Stock disponible actual: 0');
        $this->get(route('envasado.index'))->assertOk();
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk();
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
    }

    public function test_contratapa_configurada_descuenta_solo_color_elegido_y_manga_no_se_descuenta(): void
    {
        $e = $this->escenarioEnvasado();
        $verde = Insumo::create(['codigo' => 'VERDE', 'nombre' => 'Contratapa verde', 'categoria' => 'Tapas', 'unidad_medida' => 'unidad', 'cantidad_actual' => 100, 'estado' => true]);
        $roja = Insumo::create(['codigo' => 'ROJA', 'nombre' => 'Contratapa roja', 'categoria' => 'Tapas', 'unidad_medida' => 'unidad', 'cantidad_actual' => 100, 'estado' => true]);
        $manga = Insumo::create(['codigo' => 'MANGA', 'nombre' => 'Manga de prueba', 'categoria' => 'Otros', 'unidad_medida' => 'metro', 'cantidad_actual' => 100, 'estado' => true]);
        MaterialEnvasado::factory()->create(['configuracion_envasado_id' => $e['configuracion']->id, 'insumo_id' => $verde->id, 'funcion' => 'contratapa']);
        MaterialEnvasado::factory()->create(['configuracion_envasado_id' => $e['configuracion']->id, 'insumo_id' => $manga->id, 'funcion' => 'manga', 'base_consumo' => 'empaque', 'cantidad_por_unidad' => '0.650', 'unidades_por_empaque' => 5, 'tipo_empaque' => 'Paquete']);
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $this->datos($e))->assertRedirect();
        $this->assertSame('88.000', $verde->fresh()->cantidad_actual);
        $this->assertSame('100.000', $roja->fresh()->cantidad_actual);
        $this->assertSame('100.000', $manga->fresh()->cantidad_actual);
        $this->assertDatabaseCount('envasado_consumos', 2);
    }

    public function test_configuracion_historica_no_cambia_y_se_rechaza_version_manipulada(): void
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $this->datos($e))->assertRedirect();
        $e['configuracion']->materiales()->update(['cantidad_por_unidad' => 2]);
        $e['configuracion']->increment('version');
        $this->post(route('envasado.store'), array_replace($this->datos($e), ['version_configuracion' => 0]))->assertSessionHasErrors('configuracion_envasado_id');
        $this->assertDatabaseHas('envasado_consumos', ['cantidad_por_unidad' => '1.000', 'cantidad' => '12.000']);
    }

    public function test_sin_configuracion_valida_no_registra_y_rechaza_cantidad_no_entera(): void
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario']);
        foreach ([0, -1, '1.5', 'texto', 1000001] as $cantidad) {
            $d = $this->datos($e);
            $d['cantidad'] = $cantidad;
            $this->post(route('envasado.store'), $d)->assertSessionHasErrors('cantidad');
        }
        $e['configuracion']->materiales()->update(['estado' => false]);
        $this->post(route('envasado.store'), $this->datos($e))->assertSessionHasErrors('configuracion_envasado_id');
        $this->assertDatabaseCount('envasados', 0);
    }

    public function test_producto_sin_inventario_se_crea_solo_al_confirmar(): void
    {
        $e = $this->escenarioEnvasado();
        Inventario::where('presentacion_producto_id', $e['presentacion']->id)->delete();
        $this->actingAs($e['usuario'])->post(route('envasado.previsualizar'), $this->datos($e))->assertOk();
        $this->assertDatabaseCount('inventarios', 0);
        $this->post(route('envasado.store'), $this->datos($e))->assertRedirect();
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 12]);
    }

    public function test_despacho_posterior_no_vuelve_a_consumir_insumos(): void
    {
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e, 'listo_despacho');
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $this->datos($e))->assertRedirect();
        $this->post(route('cumplimiento.despachar', $pedido))->assertRedirect();
        $this->assertSame('88.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 7]);
        $this->assertTrue(app(VerificadorInventario::class)->revisar()['correcto']);
    }

    public function test_permisos_registro_y_consulta_se_aplican(): void
    {
        $e = $this->escenarioEnvasado();
        $e['usuario']->role->permisos()->detach();
        $this->actingAs($e['usuario'])->get(route('envasado.index'))->assertForbidden();
        $this->post(route('envasado.store'), $this->datos($e))->assertForbidden();
        $this->assertDatabaseCount('envasados', 0);
    }
}
