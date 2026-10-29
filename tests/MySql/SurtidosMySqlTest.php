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

    public function test_flujo_integral_mixto_conserva_historicos_y_solo_reincorpora_retornos_aptos_aprobados(): void
    {
        $e = $this->escenarioSurtido();
        $historico = $this->pedidoEnvasado($e, 'entregado');
        $normal = $this->pedidoEnvasado($e, 'registrado');
        $originales = [];
        foreach ([$historico, $normal] as $control) {
            $control->refresh();
            $originales[$control->id] = [$control->getAttributes(), $control->detalles->map->getAttributes()->all()];
        }
        $this->post(route('pedidos.store'), ['cliente_id' => $normal->cliente_id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado', 'detalles' => [
            ['presentacion_producto_id' => $e['skus'][0]->id, 'modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'cantidad' => 1],
            ['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'], 'modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'cantidad' => 2, 'paquetes_json' => json_encode($this->paquetesSurtidos($e))],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::latest('id')->firstOrFail();
        $this->assertSame('72.00', $pedido->total);
        $this->assertSame(2, $pedido->detalles->count());
        $this->post(route('pedidos.procesar', $pedido), ['accion' => 'confirmar'])->assertSessionHasNoErrors();
        $lista = $this->get(route('cumplimiento.lista-preparacion'))->assertOk();
        $filas = $lista->viewData('filas')->filter(fn (array $fila): bool => array_intersect(array_column($fila['presentaciones'], 'id'), array_column($e['skus'], 'id')) !== []);
        $this->assertSame(36, $filas->sum('total_unidades'));
        $this->assertSame([19, 11, 6], $filas->flatMap(fn (array $fila): array => array_values($fila['presentaciones']))->sortBy('id')->pluck('solicitadas')->values()->all());
        $this->post(route('cumplimiento.iniciar-preparacion', $pedido))->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.finalizar-preparacion', $pedido))->assertSessionHasNoErrors();
        $stockPreparado = array_map(fn ($sku): int => (int) Inventario::where('presentacion_producto_id', $sku->id)->value('cantidad_actual'), $e['skus']);
        $this->assertSame([100, 100, 100], $stockPreparado);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasErrors('estado');
        $stockDespachado = array_map(fn ($sku): int => (int) Inventario::where('presentacion_producto_id', $sku->id)->value('cantidad_actual'), $e['skus']);
        $this->assertSame([81, 89, 94], $stockDespachado);
        $this->assertSame(3, MovimientoInventario::where('pedido_id', $pedido->id)->count());
        $fisicas = app(ControlEntregaPedido::class)->resumen($pedido->fresh())['lineas'];
        $rechazadas = [];
        $datos = [];
        foreach ($fisicas as $linea) {
            $rechazo = isset($linea['componente_id']) && $linea['presentacion_id'] === $e['skus'][0]->id && $linea['despachadas'] === 5 ? 2 : (isset($linea['componente_id']) && $linea['presentacion_id'] === $e['skus'][1]->id && $linea['despachadas'] === 7 ? 1 : 0);
            $fila = ['pedido_detalle_id' => $linea['id'], 'aceptadas' => $linea['despachadas'] - $rechazo, 'rechazadas' => $rechazo, 'motivo' => 'El cliente informa un rechazo que requiere revisión comercial.'];
            if (isset($linea['componente_id'])) {
                $fila['despacho_componente_id'] = $linea['componente_id'];
            }
            $datos[] = $fila;
            if ($rechazo > 0) {
                $rechazadas[] = ['pedido_detalle_id' => $linea['id'], 'despacho_componente_id' => $linea['componente_id'], 'aptas' => 1, 'danadas' => $rechazo - 1, 'en_revision' => 0, 'no_recuperadas' => 0];
            }
        }
        $this->post(route('cumplimiento.resultado-entrega', $pedido), ['version' => 0, 'detalles' => $datos])->assertSessionHasNoErrors();
        $this->assertSame('entrega_parcial', $pedido->fresh()->estado);
        $resultadoEntrega = app(ControlEntregaPedido::class)->resumen($pedido->fresh())['lineas'];
        $this->assertSame(33, array_sum(array_column($resultadoEntrega, 'aceptadas')));
        $this->assertSame(3, array_sum(array_column($resultadoEntrega, 'rechazadas')));
        $stockRechazado = array_map(fn ($sku): int => (int) Inventario::where('presentacion_producto_id', $sku->id)->value('cantidad_actual'), $e['skus']);
        $this->assertSame([81, 89, 94], $stockRechazado);
        $this->post(route('cumplimiento.retornos', $pedido), ['version' => 1, 'observaciones' => 'Recepción física de dos unidades aptas y una unidad dañada.', 'detalles' => $rechazadas])->assertSessionHasNoErrors();
        $stockRecibido = array_map(fn ($sku): int => (int) Inventario::where('presentacion_producto_id', $sku->id)->value('cantidad_actual'), $e['skus']);
        $this->assertSame([81, 89, 94], $stockRecibido);
        $retornos = DB::table('retorno_pedido_detalles')->whereIn('despacho_componente_id', array_column($rechazadas, 'despacho_componente_id'))->orderBy('id')->get();
        foreach ($retornos as $indice => $retorno) {
            $this->post(route('cumplimiento.aprobar-retorno', $pedido), ['version' => 2 + $indice, 'retorno_detalle_id' => $retorno->id, 'cantidad' => 1, 'motivo' => 'Se comprueba la recepción física y aptitud de esta unidad.'])->assertSessionHasNoErrors();
        }
        $stockReincorporado = array_map(fn ($sku): int => (int) Inventario::where('presentacion_producto_id', $sku->id)->value('cantidad_actual'), $e['skus']);
        $this->assertSame([82, 90, 94], $stockReincorporado);
        $this->assertSame(2, MovimientoInventario::where('origen_tipo', 'retorno_pedido')->whereIn('presentacion_producto_id', array_column($e['skus'], 'id'))->count());
        $this->assertSame('72.00', $pedido->fresh()->total);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        foreach ($originales as $id => $original) {
            $control = Pedido::findOrFail($id);
            $this->assertSame($original, [$control->getAttributes(), $control->detalles->map->getAttributes()->all()]);
        }
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
        $migracionesSurtidos = array_map(fn (string $archivo): string => database_path('migrations/'.$archivo), [
            '2026_10_24_095908_create_formatos_surtidos_tables.php',
            '2026_10_24_095909_create_paquetes_surtidos_tables.php',
            '2026_10_24_095910_add_componentes_surtidos_to_entregas.php',
            '2026_10_24_102126_protect_surtidos_integridad.php',
        ]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--path' => $migracionesSurtidos, '--realpath' => true, '--force' => true, '--no-interaction' => true]));
        $this->assertSame(45, DB::table('migrations')->count());
        foreach ($antes as $tabla => [$columnas, $filas]) {
            $this->assertSame($filas, DB::table($tabla)->select($columnas)->get()->map(fn ($r): string => json_encode($r))->sort()->values()->all(), $tabla.' cambió registros históricos al incorporar surtidos.');
        }
        $migracionesUnidades = array_map(fn (string $archivo): string => database_path('migrations/'.$archivo), [
            '2026_10_29_033324_create_versiones_y_agrupaciones_pedidos.php',
            '2026_10_29_033326_create_despachos_parciales_pedido.php',
        ]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--path' => $migracionesUnidades, '--realpath' => true, '--force' => true, '--no-interaction' => true]));
        $this->assertSame(47, DB::table('migrations')->count());
        foreach ($antes as $tabla => [$columnas, $filas]) {
            $this->assertSame($filas, DB::table($tabla)->select($columnas)->get()->map(fn ($r): string => json_encode($r))->sort()->values()->all(), $tabla.' cambió registros históricos.');
        }
        $this->assertSame('normal', DB::table('pedido_detalles')->where('pedido_id', $pedido->id)->value('tipo_linea'));
        $this->assertSame(0, DB::table('pedido_paquetes_surtidos')->count());
        $this->assertNull(DB::table('pedidos')->where('id', $pedido->id)->value('modelo_comercial'));
        $this->assertSame(0, (int) DB::table('pedido_detalles')->where('pedido_id', $pedido->id)->value('cantidad_espera'));
        foreach (['pedido_versiones', 'pedido_agrupaciones', 'pedido_agrupacion_detalles', 'pedido_preparaciones', 'pedido_despachos', 'pedido_despacho_lineas'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), $tabla.' no debe generar datos retroactivos.');
        }
        $this->assertTrue(app(ControlEntregaPedido::class)->resumen($pedido->fresh())['historico_sin_desglose']);
    }
}
