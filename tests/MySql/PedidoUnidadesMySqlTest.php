<?php

namespace Tests\MySql;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Services\AtenderPedidoUnidades;
use App\Services\GestionarPedidoUnidades;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\SurtidosEscenario;

class PedidoUnidadesMySqlTest extends PruebaInventarioMySql
{
    use SurtidosEscenario;

    private function escenario(): array
    {
        $e = $this->escenarioSurtido(6);
        $cliente = Cliente::create(['nombre' => 'Cliente ficticio por unidades', 'estado' => true]);
        $e['pedido'] = app(GestionarPedidoUnidades::class)->guardar([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => today('America/Lima')->toDateString(),
            'fecha_entrega' => today('America/Lima')->toDateString(),
            'condicion_pago' => 'contado',
            'detalles' => [['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 6]],
            'agrupaciones' => [['capacidad' => 8, 'componentes' => [['sku_id' => $e['presentacion']->id, 'cantidad' => 3]]]],
        ], $e['usuario']);
        $e['pedido']->update(['estado' => 'en_preparacion']);
        $e['pedido']->refresh();
        $e['detalle'] = $e['pedido']->detalles()->sole();
        app(AtenderPedidoUnidades::class)->preparar($e['pedido'], [
            'version_pedido' => $e['pedido']->version,
            'pedido_detalle_id' => $e['detalle']->id,
            'cantidad' => 6,
            'motivo' => 'Se verificaron seis unidades físicamente preparadas.',
        ], $e['usuario']);
        $e['pedido']->refresh();

        return $e;
    }

    private function salida(array $e, string $identidad): array
    {
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$e['pedido']]))[$e['pedido']->id];

        return ['version_pedido' => $e['pedido']->version, 'operacion' => $identidad.'_'.$e['pedido']->id,
            'paquetes' => $e['pedido']->estado === 'despacho_parcial' ? [$foto['agrupaciones'][0]['identidad']] : [],
            'motivo' => 'Salida parcial de tres unidades físicamente preparadas.',
            'detalles' => [['id' => $e['detalle']->id, 'cantidad' => 3]]];
    }

    public function test_operaciones_simultaneas_con_misma_identidad_no_duplican_la_salida(): void
    {
        $e = $this->escenario();
        $ruta = '/cumplimiento/'.$e['pedido']->id.'/despacho-real';
        $datos = $this->salida($e, 'misma_salida_mysql_00001');
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$e['pedido']]))[$e['pedido']->id];
        $datos['paquetes'] = [$foto['agrupaciones'][0]['identidad']];
        $operaciones = [
            $this->operacion($e['usuario'], $ruta, $datos),
            $this->operacion($e['usuario'], $ruta, $datos),
        ];
        $resultados = $this->simultaneas('pedidos', $e['pedido']->id, $operaciones);

        $this->assertSame([302, 302], array_column($resultados, 'estado'));
        $this->assertSame(1, DB::table('pedido_despachos')->where('pedido_id', $e['pedido']->id)->count());
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 3]);
        $ids = DB::table('pedido_despachos')->where('pedido_id', $e['pedido']->id)->pluck('id');
        $this->assertSame(1, DB::table('movimientos_inventario')->whereIn('despacho_unidades_id', $ids)->count());
        $this->assertSame('12.00', $e['pedido']->fresh()->total);
        $this->assertSame('despacho_parcial', $e['pedido']->fresh()->estado);
        $enlace = (array) DB::table('pedido_despacho_agrupaciones')->where('pedido_id', $e['pedido']->id)->sole();
        unset($enlace['id']);
        try {
            DB::table('pedido_despacho_agrupaciones')->insert($enlace);
            $this->fail('El índice único debe impedir duplicar el paquete también en MySQL.');
        } catch (QueryException $excepcion) {
            $this->assertSame('23000', (string) $excepcion->getCode());
        }
        try {
            DB::table('pedido_despacho_agrupaciones')->insert(array_replace($enlace, ['identidad' => null]));
            $this->fail('Una identidad NULL no debe eludir el índice.');
        } catch (QueryException $excepcion) {
            $this->assertSame('23000', (string) $excepcion->getCode());
        }
        $this->assertSame(1, DB::table('pedido_despacho_agrupaciones')->where('pedido_id', $e['pedido']->id)->count());
        $skuId = $e['presentacion']->id;
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertViewHas('filas', function ($filas) use ($skuId): bool {
            $presentacion = $filas->flatMap(fn (array $fila): array => $fila['presentaciones'])->firstWhere('id', $skuId);

            return $presentacion !== null && $presentacion['solicitadas'] === 3 && $presentacion['preparadas'] === 3
                && $presentacion['agrupadas'] === 0 && $presentacion['paquetes'] === 0 && $presentacion['stock'] === 3;
        });
    }

    public function test_version_obsoleta_no_duplica_y_nuevo_parcial_legitimo_sigue_permitido(): void
    {
        $e = $this->escenario();
        $ruta = '/cumplimiento/'.$e['pedido']->id.'/despacho-real';
        $resultados = $this->simultaneas('pedidos', $e['pedido']->id, [
            $this->operacion($e['usuario'], $ruta, $this->salida($e, 'salida_distinta_mysql_00001')),
            $this->operacion($e['usuario'], $ruta, $this->salida($e, 'salida_distinta_mysql_00002')),
        ]);
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $e['pedido'] = Pedido::findOrFail($e['pedido']->id);
        app(AtenderPedidoUnidades::class)->despachar($e['pedido'], $this->salida($e, 'salida_legitima_mysql_00003'), $e['usuario']);

        $this->assertSame(2, DB::table('pedido_despachos')->where('pedido_id', $e['pedido']->id)->count());
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 0]);
        $this->assertSame(6, (int) app(AtenderPedidoUnidades::class)->despachadas($e['pedido']->id)->get($e['detalle']->id));
        $this->assertSame('despachado', $e['pedido']->fresh()->estado);
    }
}
