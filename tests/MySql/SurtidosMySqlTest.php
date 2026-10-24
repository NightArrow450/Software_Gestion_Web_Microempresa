<?php

namespace Tests\MySql;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarSurtidos;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\SurtidosEscenario;

class SurtidosMySqlTest extends PruebaInventarioMySql
{
    use SurtidosEscenario;

    private function surtido(array $e, string $estado = 'listo_despacho'): Pedido
    {
        $pedido = $this->pedidoEnvasado($e, 'registrado');
        $pedido->detalles()->delete();
        $servicio = app(GestionarSurtidos::class);
        $d = $pedido->detalles()->create($servicio->prepararLinea(['formato_surtido_id' => $e['formato'], 'modalidad' => 'empaque', 'cantidad' => 2], $e['usuario']));
        $servicio->guardar($d, $this->paquetesSurtidos($e), $e['usuario'], 'Composición de prueba independiente para concurrencia.');
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => $estado, 'total' => 48]);

        return $pedido->fresh();
    }

    public function test_dos_despachos_compiten_por_sku_y_el_que_falla_no_deja_descuentos_parciales(): void
    {
        $e = $this->escenarioSurtido(12);
        $pedidos = [$this->surtido($e), $this->surtido($e)];
        $operaciones = array_map(fn (Pedido $p): array => $this->operacion($e['usuario'], '/cumplimiento/'.$p->id.'/despachar', []), $pedidos);
        $respuestas = $this->simultaneas('presentaciones_producto', $e['skus'][0]->id, $operaciones);
        $estados = array_column($respuestas, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame([5, 1, 6], array_map(fn ($p): int => (int) Inventario::where('presentacion_producto_id', $p->id)->value('cantidad_actual'), $e['skus']));
        $this->assertSame(3, MovimientoInventario::whereIn('pedido_id', array_column($pedidos, 'id'))->count());
        $this->assertSame(6, DB::table('pedido_despacho_componentes')->whereIn('pedido_detalle_id', $pedidos[0]->detalles->pluck('id')->merge($pedidos[1]->detalles->pluck('id')))->count());
    }

    public function test_dos_modificaciones_simultaneas_conservan_una_sola_version_vigente(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->surtido($e, 'confirmado');
        $d = $pedido->detalles->sole();
        $paquetes = $this->paquetesSurtidos($e);
        $paquetes[0]['componentes'][0]['cantidad'] = 6;
        $paquetes[0]['componentes'][1]['cantidad'] = 3;
        $datos = ['version' => $pedido->version, 'paquetes_json' => json_encode($paquetes), 'motivo' => 'Revisión comercial concurrente de composición.'];
        $ruta = '/pedidos/'.$pedido->id.'/surtidos/'.$d->id;
        $respuestas = $this->simultaneas('pedidos', $pedido->id, [$this->operacion($e['usuario'], $ruta, $datos), $this->operacion($e['usuario'], $ruta, $datos)]);
        $estados = array_column($respuestas, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(3, DB::table('paquete_surtido_composiciones')->whereIn('paquete_surtido_id', DB::table('pedido_paquetes_surtidos')->where('pedido_detalle_id', $d->id)->select('id'))->count());
        $this->assertSame(6, app(GestionarSurtidos::class)->paquetes($d)[0]['componentes'][0]['cantidad']);
    }

    public function test_aprobaciones_concurrentes_no_duplican_stock_y_admiten_otro_retorno_parcial_legitimo(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->surtido($e);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $d = $pedido->detalles->sole();
        $c = DB::table('pedido_despacho_componentes')->where('pedido_detalle_id', $d->id)->where('presentacion_producto_id', $e['skus'][0]->id)->orderBy('id')->first();
        $this->post(route('cumplimiento.resultado-entrega', $pedido), ['version' => 0, 'detalles' => [['pedido_detalle_id' => $d->id, 'despacho_componente_id' => $c->id, 'aceptadas' => 2, 'rechazadas' => 3, 'motivo' => 'Rechazo de tres unidades del aroma identificado.']]])->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.retornos', $pedido), ['version' => 1, 'observaciones' => 'Recepción física comprobada de tres unidades aptas.', 'detalles' => [['pedido_detalle_id' => $d->id, 'despacho_componente_id' => $c->id, 'aptas' => 3, 'danadas' => 0, 'en_revision' => 0, 'no_recuperadas' => 0]]])->assertSessionHasNoErrors();
        $retorno = DB::table('retorno_pedido_detalles')->where('despacho_componente_id', $c->id)->first();
        $datos = ['version' => 2, 'retorno_detalle_id' => $retorno->id, 'cantidad' => 1, 'motivo' => 'Reincorporación autorizada de una unidad apta.'];
        $ruta = '/cumplimiento/'.$pedido->id.'/aprobar-retorno';
        $respuestas = $this->simultaneas('pedidos', $pedido->id, [$this->operacion($e['usuario'], $ruta, $datos), $this->operacion($e['usuario'], $ruta, $datos)]);
        $estados = array_column($respuestas, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(94, (int) Inventario::where('presentacion_producto_id', $e['skus'][0]->id)->value('cantidad_actual'));
        $this->post($ruta, array_replace($datos, ['version' => 3, 'cantidad' => 2]))->assertSessionHasNoErrors();
        $this->assertSame(96, (int) Inventario::where('presentacion_producto_id', $e['skus'][0]->id)->value('cantidad_actual'));
        $this->assertSame(2, MovimientoInventario::where('origen_tipo', 'retorno_pedido')->where('presentacion_producto_id', $e['skus'][0]->id)->count());
        $this->assertSame(89, (int) Inventario::where('presentacion_producto_id', $e['skus'][1]->id)->value('cantidad_actual'));
    }

    public function test_indices_mysql_protegen_referencias_normales_nulas_y_componentes_sin_impedir_aromas_distintos(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->surtido($e);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $d = $pedido->detalles->sole();
        $componentes = DB::table('pedido_despacho_componentes')->where('pedido_detalle_id', $d->id)->orderBy('id')->limit(2)->get();
        $this->post(route('cumplimiento.resultado-entrega', $pedido), ['version' => 0, 'detalles' => $componentes->map(fn ($c): array => ['pedido_detalle_id' => $d->id, 'despacho_componente_id' => $c->id, 'aceptadas' => 1, 'rechazadas' => 0])->all()])->assertSessionHasNoErrors();
        $filas = DB::table('entrega_pedido_detalles')->where('pedido_detalle_id', $d->id)->get();
        $this->assertCount(2, $filas);
        $normal = $this->pedidoEnvasado($e, 'listo_despacho');
        $this->post(route('cumplimiento.despachar', $normal))->assertSessionHasNoErrors();
        $nd = $normal->detalles->sole();
        $this->post(route('cumplimiento.resultado-entrega', $normal), ['version' => 0, 'detalles' => [['pedido_detalle_id' => $nd->id, 'aceptadas' => 1, 'rechazadas' => 0]]])->assertSessionHasNoErrors();
        $filaNormal = DB::table('entrega_pedido_detalles')->where('pedido_detalle_id', $nd->id)->first();
        $this->assertNull($filaNormal->despacho_componente_id);
        foreach ([$filas->first(), $filaNormal] as $fila) {
            $datos = (array) $fila;
            unset($datos['id'], $datos['referencia_fisica']);
            try {
                DB::table('entrega_pedido_detalles')->insert($datos);
                $this->fail('El índice debe impedir una referencia física repetida, también con componente NULL.');
            } catch (QueryException $exception) {
                $this->assertSame('23000', (string) $exception->getCode());
            }
        }
        $this->assertSame(2, DB::table('entrega_pedido_detalles')->where('pedido_detalle_id', $d->id)->count());
        $this->assertSame(1, DB::table('entrega_pedido_detalles')->where('pedido_detalle_id', $nd->id)->count());
    }

    public function test_historial_inmutable_tambien_se_protege_en_mysql(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->surtido($e);
        $paquete = app(GestionarSurtidos::class)->paquetes($pedido->detalles->sole())[0];
        $this->expectException(QueryException::class);
        DB::table('paquete_surtido_componentes')->where('id', $paquete['componentes'][0]['id'])->update(['cantidad' => 1]);
    }

    public function test_migraciones_sobre_41_anteriores_conservan_todas_las_columnas_y_registros_historicos(): void
    {
        $conexion = EntornoMySql::proteger();
        $nombre = $conexion['database'].'_hist';
        $this->assertLessThanOrEqual(64, strlen($nombre));
        $conexion['database'] = $nombre;
        config(['database.connections.mysql_concurrencia' => $conexion]);
        EntornoMySql::proteger();
        $servidor = new \PDO('mysql:host='.$conexion['host'].';port='.$conexion['port'].';charset=utf8mb4', $conexion['username'], $conexion['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $servidor->exec("CREATE DATABASE IF NOT EXISTS `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        EntornoMySql::conectar();
        $tablasIniciales = Schema::getTableListing($nombre, false);
        $this->assertSame([], $tablasIniciales, 'La base histórica de prueba debe ser nueva y vacía.');
        $anteriores = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $ruta): bool => basename($ruta) < '2026_10_24_095908'));
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--path' => $anteriores, '--realpath' => true, '--force' => true, '--no-interaction' => true]));
        $this->assertSame(41, DB::table('migrations')->count());
        app()->forgetInstance(GestionarSurtidos::class);
        $e = $this->escenarioEnvasado(40, 5);
        $pedido = $this->pedidoEnvasado($e, 'entregado');
        $antes = [];
        foreach (Schema::getTableListing($nombre, false) as $tabla) {
            if ($tabla !== 'migrations') {
                $columnas = Schema::getColumnListing($tabla);
                $filas = DB::table($tabla)->select($columnas)->get()->map(fn ($r): string => json_encode($r))->sort()->values()->all();
                $antes[$tabla] = [$columnas, $filas];
            }
        }
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--force' => true, '--no-interaction' => true]));
        $this->assertSame(45, DB::table('migrations')->count());
        foreach ($antes as $tabla => [$columnas, $filas]) {
            $this->assertSame($filas, DB::table($tabla)->select($columnas)->get()->map(fn ($r): string => json_encode($r))->sort()->values()->all(), $tabla.' cambió registros históricos.');
        }
        $this->assertSame('normal', DB::table('pedido_detalles')->where('pedido_id', $pedido->id)->value('tipo_linea'));
        $this->assertSame(0, DB::table('pedido_paquetes_surtidos')->count());
        $this->assertTrue(app(ControlEntregaPedido::class)->resumen($pedido->fresh())['historico_sin_desglose']);
    }
}
