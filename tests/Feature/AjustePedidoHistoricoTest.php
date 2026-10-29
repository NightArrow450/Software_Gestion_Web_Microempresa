<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Services\AjustarPedidoHistorico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class AjustePedidoHistoricoTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    private function escenario(): array
    {
        $e = $this->escenarioEnvasado(100, 100);
        $e['usuario']->role->update(['nombre' => 'Operaciones Comerciales']);
        $e['usuario']->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => 'pedidos.editar'])->id);
        $this->actingAs($e['usuario']);
        $e['pedido'] = $this->pedidoEnvasado($e);
        $e['detalle'] = $e['pedido']->detalles()->sole();
        $e['detalle']->update(['modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'precio_unitario' => 30, 'importe' => 150]);
        $e['pedido']->update(['total' => 150]);
        $e['pedido']->refresh();
        $e['datos'] = ['_version' => $e['pedido']->version, 'motivo' => 'Revisión comercial documentada del pedido histórico.',
            'detalles' => [['id' => $e['detalle']->id, 'presentacion_producto_id' => $e['presentacion']->id, 'modalidad' => 'empaque', 'cantidad' => 5, 'unidades_por_empaque' => 12]]];

        return $e;
    }

    public function test_reduce_historico_confirmado_con_precio_pactado_y_sin_borrar_detalles(): void
    {
        $e = $this->escenario();
        $e['presentacion']->update(['precio_unitario' => 9, 'unidades_por_empaque' => 24, 'estado' => false]);
        $e['datos']['detalles'][0]['cantidad'] = 3;
        $e['datos']['detalles'][0]['precio_unitario'] = '0.01';
        $ajustado = app(AjustarPedidoHistorico::class)->guardar($e['pedido'], $e['datos'], $e['usuario']);
        $this->assertSame('90.00', $ajustado->total);
        $this->assertNull($ajustado->modelo_comercial);
        $this->assertDatabaseHas('pedido_detalles', ['id' => $e['detalle']->id, 'precio_unitario' => 30, 'modalidad' => 'empaque', 'unidades_por_empaque' => 12]);
        $versiones = DB::table('pedido_versiones')->where('pedido_id', $ajustado->id)->orderBy('numero')->get();
        $this->assertCount(2, $versiones);
        $this->assertSame(5, json_decode($versiones[0]->datos, true)['lineas'][0]['cantidad']);
        $this->assertSame(3, json_decode($versiones[1]->datos, true)['lineas'][0]['cantidad']);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseHas('auditoria', ['accion' => 'pedido_historico_ajustado', 'entidad_id' => $ajustado->id]);
    }

    public function test_cantidad_fisica_no_valorable_exige_importe_comercial_explicito(): void
    {
        $e = $this->escenario();
        $e['datos']['ajustes_historicos'][$e['detalle']->id] = ['cantidad_fisica_revisada' => 32];
        try {
            app(AjustarPedidoHistorico::class)->guardar($e['pedido'], $e['datos'], $e['usuario']);
            $this->fail('No debe deducir un precio unitario de un empaque.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('ajustes_historicos', $excepcion->errors());
        }
        $this->assertDatabaseCount('pedido_versiones', 0);
        $this->assertSame('150.00', $e['pedido']->fresh()->total);
    }

    public function test_ajuste_fisico_revisado_se_prepara_imprime_y_descuenta_sin_conversion_de_precio(): void
    {
        $e = $this->escenario();
        $e['datos']['ajustes_historicos'][$e['detalle']->id] = ['cantidad_fisica_revisada' => 32, 'importe_revisado' => '64.00'];
        $pedido = app(AjustarPedidoHistorico::class)->guardar($e['pedido'], $e['datos'], $e['usuario']);
        $this->assertSame('64.00', $pedido->total);
        $this->assertDatabaseHas('pedido_detalles', ['id' => $e['detalle']->id, 'cantidad' => 5, 'modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'precio_unitario' => 30, 'cantidad_fisica_ajustada' => 32]);
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertViewHas('filas', fn ($filas): bool => $filas->sum('total_unidades') === 32);
        $this->get(route('pedidos.imprimir-por-fecha', ['fecha_pedido' => today()->toDateString()]))->assertOk()->assertSee('32 unidades físicas');
        $pedido->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 68]);
        $this->assertDatabaseHas('movimientos_inventario', ['pedido_id' => $pedido->id, 'cantidad' => 32]);
    }

    public function test_conserva_diferencia_original_de_cabecera_sin_reconciliarla_silenciosamente(): void
    {
        $e = $this->escenario();
        $e['pedido']->update(['total' => '150.04']);
        $e['datos']['_version'] = $e['pedido']->fresh()->version;
        $e['datos']['observaciones'] = 'Solo se registra una observación.';
        $pedido = app(AjustarPedidoHistorico::class)->guardar($e['pedido'], $e['datos'], $e['usuario']);
        $this->assertSame('150.04', $pedido->total);
    }

    public function test_despachado_permite_observacion_pero_no_reescribe_cantidad_fisica(): void
    {
        $e = $this->escenario();
        $e['pedido']->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $e['pedido']))->assertSessionHasNoErrors();
        $antes = DB::table('movimientos_inventario')->get()->toJson();
        $e['datos']['_version'] = $e['pedido']->fresh()->version;
        $e['datos']['observaciones'] = 'Incidencia comunicada al personal de reparto.';
        app(AjustarPedidoHistorico::class)->guardar($e['pedido'], $e['datos'], $e['usuario']);
        $e['datos']['_version'] = $e['pedido']->fresh()->version;
        $e['datos']['detalles'][0]['cantidad'] = 4;
        try {
            app(AjustarPedidoHistorico::class)->guardar($e['pedido'], $e['datos'], $e['usuario']);
            $this->fail('No se reescriben salidas físicas.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('detalles', $excepcion->errors());
        }
        $this->assertSame($antes, DB::table('movimientos_inventario')->get()->toJson());
        $this->assertDatabaseHas('pedido_detalles', ['id' => $e['detalle']->id, 'cantidad' => 5]);
        $this->get(route('pedidos.edit', $e['pedido']))->assertOk()->assertSee('Ajuste de pedido histórico');
    }
}
