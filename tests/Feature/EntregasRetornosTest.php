<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Services\ControlEntregaPedido;
use App\Services\TokenOperacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class EntregasRetornosTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    public function test_migraciones_son_aditivas_y_conservan_datos_anteriores(): void
    {
        $e = $this->escenarioEnvasado(40, 50);
        $this->pedidoEnvasado($e, 'despachado');
        $tablas = ['users', 'clientes', 'productos', 'presentaciones_producto', 'inventarios', 'insumos', 'pedidos', 'pedido_detalles', 'movimientos_inventario'];
        $antes = [];
        foreach ($tablas as $tabla) {
            $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }
        $migraciones = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $ruta): bool => str_contains($ruta, 'create_incidencias_y_entregas') || str_contains($ruta, 'create_retornos_pedido') || str_contains($ruta, 'create_incidencia_entrega_eventos')));
        foreach (array_reverse($migraciones) as $ruta) {
            (require $ruta)->down();
        }
        foreach ($migraciones as $ruta) {
            (require $ruta)->up();
        }
        foreach ($tablas as $tabla) {
            $this->assertSame($antes[$tabla], DB::table($tabla)->orderBy('id')->get()->toJson(), $tabla);
        }
        foreach (['incidencias_entrega', 'entregas_pedido', 'entrega_pedido_detalles', 'retornos_pedido', 'retorno_pedido_detalles', 'incidencia_entrega_eventos'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
    }

    private function escenarioEntrega(bool $empaque = false): array
    {
        $e = $this->escenarioEnvasado(100, 100);
        foreach (['cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar', 'cumplimiento.incidencias.revisar', 'cumplimiento.incidencias.resolver', 'cumplimiento.incidencias.corregir'] as $nombre) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }
        $pedido = $this->pedidoEnvasado($e, 'listo_despacho');
        $detalle = $pedido->detalles()->firstOrFail();
        if ($empaque) {
            $detalle->update(['modalidad' => 'empaque', 'cantidad' => 2, 'unidades_por_empaque' => 15, 'precio_unitario' => 30, 'importe' => 60]);
            $pedido->update(['total' => 60]);
        }
        $this->actingAs($e['usuario'])->post(route('cumplimiento.despachar', $pedido))->assertRedirect();

        return $e + ['pedido' => $pedido->fresh(), 'detalle' => $detalle->fresh()];
    }

    private function version(array $e): int
    {
        return (int) DB::table('incidencias_entrega')->where('pedido_id', $e['pedido']->id)->value('version');
    }

    private function resultado(array $e, int $aceptadas, int $rechazadas, string $ruta = 'cumplimiento.resultado-entrega'): TestResponse
    {
        return $this->post(route($ruta, $e['pedido']), ['version' => $this->version($e), 'observaciones' => 'Resultado informado durante la visita.', 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'aceptadas' => $aceptadas, 'rechazadas' => $rechazadas, 'motivo' => 'Cliente informa que no solicitó estas unidades.']]]);
    }

    private function retorno(array $e, int $aptas, int $danadas = 0, int $revision = 0, int $perdidas = 0): TestResponse
    {
        return $this->post(route('cumplimiento.retornos', $e['pedido']), ['version' => $this->version($e), 'observaciones' => 'Conteo físico documentado en almacén.', 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'aptas' => $aptas, 'danadas' => $danadas, 'en_revision' => $revision, 'no_recuperadas' => $perdidas]]]);
    }

    public function test_resultados_sucesivos_no_alteran_pedido_precios_ni_existencias(): void
    {
        $e = $this->escenarioEntrega();
        $antes = $e['detalle']->getAttributes();
        $this->resultado($e, 2, 1)->assertRedirect();
        $this->assertSame('entrega_pendiente', $e['pedido']->fresh()->estado);
        $this->resultado($e, 1, 1)->assertRedirect();
        $this->assertSame('entrega_parcial', $e['pedido']->fresh()->estado);
        $this->assertSame($antes, $e['detalle']->fresh()->getAttributes());
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 95]);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $r = app(ControlEntregaPedido::class)->resumen($e['pedido']);
        $this->assertSame(3, $r['lineas'][$e['detalle']->id]['aceptadas']);
        $this->assertSame(2, $r['lineas'][$e['detalle']->id]['rechazadas']);
        $this->assertSame(0, $r['lineas'][$e['detalle']->id]['pendientes']);
    }

    public function test_rechazo_total_retorno_y_aprobaciones_parciales_no_duplican_stock(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 0, 5)->assertRedirect();
        $this->assertSame('rechazado', $e['pedido']->fresh()->estado);
        $this->retorno($e, 2)->assertRedirect();
        $this->retorno($e, 2, 1)->assertRedirect();
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 95]);
        $retornos = DB::table('retorno_pedido_detalles')->orderBy('id')->get();
        foreach ([[$retornos[0]->id, 1], [$retornos[0]->id, 1], [$retornos[1]->id, 2]] as [$id, $cantidad]) {
            $datos = ['version' => $this->version($e), 'retorno_detalle_id' => $id, 'cantidad' => $cantidad, 'motivo' => 'Verificado físicamente, apto para reincorporar.', '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
            $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), $datos)->assertRedirect();
            $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), $datos)->assertRedirect();
        }
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 99]);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(3, DB::table('movimientos_inventario')->where('origen_tipo', 'retorno_pedido')->count());
        $this->assertSame(4, (int) DB::table('retorno_pedido_detalles')->sum('reincorporadas'));
        $this->assertSame('rechazado', $e['pedido']->fresh()->estado);
    }

    public function test_unidad_dentro_de_caja_y_cambio_catalogo_respetan_fotografia_historica(): void
    {
        $e = $this->escenarioEntrega(true);
        $e['presentacion']->update(['unidades_por_empaque' => 100]);
        $this->resultado($e, 29, 1)->assertRedirect();
        $r = app(ControlEntregaPedido::class)->resumen($e['pedido'])['lineas'][$e['detalle']->id];
        $this->assertSame(30, $r['despachadas']);
        $this->assertSame(5800, $r['importe_aceptado_centimos']);
        $this->assertSame(200, $r['importe_rechazado_centimos']);
        $this->assertSame(0, $r['importe_pendiente_centimos']);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 70]);
    }

    public function test_no_supera_despachado_no_rectifica_sin_permiso_y_no_reduce_rechazo_retornado(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 4, 2)->assertSessionHasErrors('detalles');
        $this->resultado($e, 3, 2)->assertRedirect();
        $this->retorno($e, 1)->assertRedirect();
        $this->resultado($e, 2, -2, 'cumplimiento.rectificar-entrega')->assertSessionHasErrors('detalles');
        $this->resultado($e, 1, -1, 'cumplimiento.rectificar-entrega')->assertRedirect();
        $this->assertSame(2, DB::table('entrega_pedido_detalles')->count());
    }
}
