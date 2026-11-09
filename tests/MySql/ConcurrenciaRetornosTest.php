<?php

namespace Tests\MySql;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Permiso;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\EnvasadoEscenario;

class ConcurrenciaRetornosTest extends PruebaInventarioMySql
{
    use EnvasadoEscenario;

    private function escenario(bool $recibir = true): array
    {
        $e = $this->escenarioEnvasado(100, 100);
        foreach (['cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar'] as $nombre) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }
        $pedido = $this->pedidoEnvasado($e, 'listo_despacho');
        $detalle = $pedido->detalles()->firstOrFail();
        $this->actingAs($e['usuario'])->post(route('cumplimiento.despachar', $pedido))->assertRedirect();
        $this->post(route('cumplimiento.resultado-entrega', $pedido), ['version' => 0, 'detalles' => [['pedido_detalle_id' => $detalle->id, 'aceptadas' => 0, 'rechazadas' => 5, 'motivo' => 'Cliente no acepta las unidades indicadas.']]])->assertRedirect();
        $incidenciaId = DB::table('incidencias_entrega')->where('pedido_id', $pedido->id)->value('id');
        if ($recibir) {
            $this->post(route('cumplimiento.retornos', $pedido), ['version' => 1, 'observaciones' => 'Recepción física comprobada en almacén.', 'detalles' => [['pedido_detalle_id' => $detalle->id, 'aptas' => 5, 'danadas' => 0, 'en_revision' => 0, 'no_recuperadas' => 0]]])->assertRedirect();
        }
        $retornoId = DB::table('retorno_pedido_detalles as d')->join('retornos_pedido as r', 'r.id', '=', 'd.retorno_id')->where('r.incidencia_id', $incidenciaId)->value('d.id');

        return $e + compact('pedido', 'detalle', 'incidenciaId', 'retornoId');
    }

    private function aprobacion(array $e, int $cantidad): array
    {
        return ['version' => 2, 'retorno_detalle_id' => $e['retornoId'], 'cantidad' => $cantidad, 'motivo' => 'Reincorporación física autorizada en almacén.'];
    }

    public function test_reenvio_simultaneo_de_aprobacion_se_reincorpora_una_sola_vez(): void
    {
        $e = $this->escenario();
        $op = $this->operacion($e['usuario'], '/cumplimiento/'.$e['pedido']->id.'/aprobar-retorno', $this->aprobacion($e, 2));
        $res = $this->simultaneas('pedidos', $e['pedido']->id, [$op, $op]);
        $this->assertSame([302, 302], array_column($res, 'estado'));
        $this->assertSame(97, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $this->assertSame(2, (int) DB::table('retorno_pedido_detalles')->where('id', $e['retornoId'])->value('reincorporadas'));
        $this->assertSame(1, MovimientoInventario::where('presentacion_producto_id', $e['presentacion']->id)->where('origen_tipo', 'retorno_pedido')->count());
    }

    public function test_tokens_distintos_version_obsoleta_y_luego_otra_aprobacion_legitima(): void
    {
        $e = $this->escenario();
        $ruta = '/cumplimiento/'.$e['pedido']->id.'/aprobar-retorno';
        $ops = array_map(fn (): array => $this->operacion($e['usuario'], $ruta, $this->aprobacion($e, 3)), [1, 2]);
        $res = $this->simultaneas('pedidos', $e['pedido']->id, $ops);
        $estados = array_column($res, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(98, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $datos = $this->aprobacion($e, 2);
        $datos['version'] = 3;
        $this->post($ruta, $datos)->assertRedirect();
        $this->assertSame(100, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $entradas = MovimientoInventario::where('presentacion_producto_id', $e['presentacion']->id)->where('origen_tipo', 'retorno_pedido')->get();
        $this->assertCount(2, $entradas);
        $this->assertCount(2, $entradas->pluck('origen_id')->unique());
        $this->assertSame('rechazado', $e['pedido']->fresh()->estado);
    }

    public function test_dos_recepciones_concurrentes_no_reutilizan_el_mismo_rechazo(): void
    {
        $e = $this->escenario(false);
        $datos = ['version' => 1, 'observaciones' => 'Recepción física comprobada en almacén.', 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'aptas' => 3, 'danadas' => 0, 'en_revision' => 0, 'no_recuperadas' => 0]]];
        $ops = array_map(fn (): array => $this->operacion($e['usuario'], '/cumplimiento/'.$e['pedido']->id.'/retornos', $datos), [1, 2]);
        $res = $this->simultaneas('pedidos', $e['pedido']->id, $ops);
        $estados = array_column($res, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(3, (int) DB::table('retorno_pedido_detalles')->where('pedido_detalle_id', $e['detalle']->id)->sum('aptas'));
        $this->assertSame(95, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
    }

    public function test_indice_unico_rechaza_otra_entrada_del_mismo_origen_con_pedido_id_null(): void
    {
        $e = $this->escenario();
        $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), $this->aprobacion($e, 2))->assertRedirect();
        $entrada = MovimientoInventario::where('presentacion_producto_id', $e['presentacion']->id)->where('origen_tipo', 'retorno_pedido')->firstOrFail();
        $this->assertNull($entrada->pedido_id);
        $this->expectException(QueryException::class);
        $entrada->replicate()->save();
    }

    public function test_restriccion_mysql_impide_reincorporar_mas_de_las_aptas_recibidas(): void
    {
        $e = $this->escenario();
        $this->expectException(QueryException::class);
        DB::table('retorno_pedido_detalles')->where('id', $e['retornoId'])->update(['reincorporadas' => 6]);
    }
}
