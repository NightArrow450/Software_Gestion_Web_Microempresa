<?php

namespace Tests\MySql;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Services\AtenderPedidoUnidades;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarPedidoUnidades;
use App\Services\LecturaPedido;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\VentaUnidadesEscenario;

class SurtidosMySqlTest extends PruebaInventarioMySql
{
    use VentaUnidadesEscenario;

    private function preparado(array $e, array $cantidades = [7, 11, 6]): Pedido
    {
        $p = $this->registrarUnidades($e, $cantidades, $this->agrupacionesUnidades($e));
        $p->update(['estado' => 'confirmado']);
        $p->refresh();
        foreach ($p->detalles()->get() as $d) {
            app(AtenderPedidoUnidades::class)->preparar($p, ['version_pedido' => $p->version, 'pedido_detalle_id' => $d->id, 'cantidad' => $d->cantidad,
                'motivo' => 'Verificación física previa de las unidades que se despacharán.'], $e['usuario']);
            $p->refresh();
        }

        return $p;
    }

    private function salida(Pedido $p): array
    {
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$p]))[$p->id];

        return ['version_pedido' => $p->version, 'operacion' => (string) Str::ulid(), 'motivo' => 'Salida física de unidades comprobadas en el entorno aislado.',
            'paquetes' => array_column($foto['agrupaciones'], 'identidad'), 'detalles' => $p->detalles()->get()->map(fn ($d): array => ['id' => $d->id, 'cantidad' => $d->cantidad])->all()];
    }

    private function versionEntrega(Pedido $p): int
    {
        return app(ControlEntregaPedido::class)->resumen($p->fresh())['version'];
    }

    public function test_dos_despachos_compiten_por_sku_y_el_que_falla_no_deja_descuentos_parciales(): void
    {
        $e = $this->escenarioUnidades(12);
        $pedidos = [$this->preparado($e), $this->preparado($e)];
        $operaciones = array_map(fn (Pedido $p): array => $this->operacion($e['usuario'], '/cumplimiento/'.$p->id.'/despacho-real', $this->salida($p)), $pedidos);
        $inventario = Inventario::where('presentacion_producto_id', $e['skus'][0]->id)->sole();
        $respuestas = $this->simultaneas('inventarios', $inventario->id, $operaciones);
        $estados = array_column($respuestas, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados, json_encode($respuestas, JSON_UNESCAPED_UNICODE));
        $this->assertSame([5, 1, 6], array_map(fn ($sku): int => (int) Inventario::where('presentacion_producto_id', $sku->id)->value('cantidad_actual'), $e['skus']));
        $despachos = DB::table('pedido_despachos')->whereIn('pedido_id', array_column($pedidos, 'id'))->pluck('id');
        $this->assertSame(3, MovimientoInventario::whereIn('despacho_unidades_id', $despachos)->count());
        $this->assertSame(3, DB::table('pedido_despacho_lineas')->whereIn('despacho_id', $despachos)->count());
        $this->assertCount(1, $despachos);
    }

    public function test_dos_cambios_solo_logisticos_con_total_igual_conservan_una_sola_version_vigente(): void
    {
        $e = $this->escenarioUnidades();
        $p = $this->registrarUnidades($e, [7, 11, 6], $this->agrupacionesUnidades($e));
        $version = $p->version;
        $grupos = $this->agrupacionesUnidades($e);
        $grupos[0]['capacidad'] = 15;
        $datos = ['cliente_id' => $p->cliente_id, 'fecha_pedido' => $p->fecha_pedido->toDateString(), 'fecha_entrega' => $p->fecha_entrega->toDateString(), 'condicion_pago' => $p->condicion_pago,
            '_version' => $version, '_updated_at' => $p->getRawOriginal('updated_at'), 'motivo' => 'Revisión concurrente únicamente logística sin cambiar precios ni cantidades.', 'agrupaciones_json' => json_encode($grupos),
            'detalles' => $p->detalles()->get()->map(fn ($d): array => ['id' => $d->id, 'presentacion_producto_id' => $d->presentacion_producto_id, 'cantidad' => $d->cantidad])->all()];
        $op = $this->operacion($e['usuario'], '/pedidos/'.$p->id, $datos) + ['metodo' => 'PUT'];
        $op2 = $this->operacion($e['usuario'], '/pedidos/'.$p->id, $datos) + ['metodo' => 'PUT'];
        $respuestas = $this->simultaneas('pedidos', $p->id, [$op, $op2]);
        $estados = array_column($respuestas, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados, json_encode($respuestas, JSON_UNESCAPED_UNICODE));
        $this->assertSame((int) $version + 1, (int) $p->fresh()->version);
        $this->assertSame('48.00', $p->fresh()->total);
        $this->assertSame(2, DB::table('pedido_versiones')->where('pedido_id', $p->id)->count());
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$p]))[$p->id];
        $this->assertSame(15, $foto['agrupaciones'][0]['capacidad']);
    }

    public function test_aprobaciones_concurrentes_no_duplican_stock_y_admiten_otro_retorno_parcial_legitimo(): void
    {
        $e = $this->escenarioUnidades();
        $p = $this->preparado($e);
        $this->post(route('pedidos.unidades.despachar', $p), $this->salida($p))->assertSessionHasNoErrors();
        $fisica = collect(app(ControlEntregaPedido::class)->resumen($p->fresh())['lineas'])->firstWhere('presentacion_id', $e['skus'][0]->id);
        $this->post(route('cumplimiento.resultado-entrega', $p), ['version' => $this->versionEntrega($p), 'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aceptadas' => 2, 'rechazadas' => 3, 'motivo' => 'Rechazo de tres unidades del SKU físico identificado.']]])->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.retornos', $p), ['version' => $this->versionEntrega($p), 'observaciones' => 'Recepción física comprobada de tres unidades aptas.', 'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aptas' => 3, 'danadas' => 0, 'en_revision' => 0, 'no_recuperadas' => 0]]])->assertSessionHasNoErrors();
        $retorno = DB::table('retorno_pedido_detalles')->where('pedido_detalle_id', $fisica['id'])->sole();
        $datos = ['version' => $this->versionEntrega($p), 'retorno_detalle_id' => $retorno->id, 'cantidad' => 1, 'motivo' => 'Reincorporación autorizada de una unidad físicamente apta.'];
        $ruta = '/cumplimiento/'.$p->id.'/aprobar-retorno';
        $respuestas = $this->simultaneas('pedidos', $p->id, [$this->operacion($e['usuario'], $ruta, $datos), $this->operacion($e['usuario'], $ruta, $datos)]);
        $estados = array_column($respuestas, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados, json_encode($respuestas, JSON_UNESCAPED_UNICODE));
        $this->assertSame(94, (int) Inventario::where('presentacion_producto_id', $e['skus'][0]->id)->value('cantidad_actual'));
        $this->post($ruta, array_replace($datos, ['version' => $this->versionEntrega($p), 'cantidad' => 2]))->assertSessionHasNoErrors();
        $this->assertSame(96, (int) Inventario::where('presentacion_producto_id', $e['skus'][0]->id)->value('cantidad_actual'));
        $this->assertSame(2, MovimientoInventario::where('origen_tipo', 'retorno_pedido')->where('presentacion_producto_id', $e['skus'][0]->id)->count());
        $this->assertSame(89, (int) Inventario::where('presentacion_producto_id', $e['skus'][1]->id)->value('cantidad_actual'));
    }

    public function test_indices_mysql_protegen_referencias_nulas_sin_impedir_lineas_de_aromas_distintos(): void
    {
        $e = $this->escenarioUnidades();
        $p = $this->preparado($e);
        $this->post(route('pedidos.unidades.despachar', $p), $this->salida($p))->assertSessionHasNoErrors();
        $fisicas = collect(app(ControlEntregaPedido::class)->resumen($p->fresh())['lineas'])->take(2);
        $this->post(route('cumplimiento.resultado-entrega', $p), ['version' => $this->versionEntrega($p), 'detalles' => $fisicas->map(fn ($f): array => ['pedido_detalle_id' => $f['id'], 'aceptadas' => 1, 'rechazadas' => 0])->values()->all()])->assertSessionHasNoErrors();
        $filas = DB::table('entrega_pedido_detalles')->whereIn('pedido_detalle_id', $fisicas->pluck('id'))->get();
        $this->assertCount(2, $filas);
        $normal = $this->pedidoEnvasado($e, 'listo_despacho');
        $this->post(route('cumplimiento.despachar', $normal))->assertSessionHasNoErrors();
        $nd = $normal->detalles->sole();
        $this->post(route('cumplimiento.resultado-entrega', $normal), ['version' => 0, 'detalles' => [['pedido_detalle_id' => $nd->id, 'aceptadas' => 1, 'rechazadas' => 0]]])->assertSessionHasNoErrors();
        $filaNormal = DB::table('entrega_pedido_detalles')->where('pedido_detalle_id', $nd->id)->sole();
        $this->assertNull($filaNormal->despacho_componente_id);
        foreach ([$filas->first(), $filaNormal] as $fila) {
            $datos = (array) $fila;
            unset($datos['id'], $datos['referencia_fisica']);
            try {
                DB::table('entrega_pedido_detalles')->insert($datos);
                $this->fail('Debe impedir la repetición de una referencia física incluso con componente NULL.');
            } catch (QueryException $error) {
                $this->assertSame('23000', (string) $error->getCode());
            }
        }
        $this->assertSame(2, DB::table('entrega_pedido_detalles')->whereIn('pedido_detalle_id', $fisicas->pluck('id'))->count());
        $this->assertSame(1, DB::table('entrega_pedido_detalles')->where('pedido_detalle_id', $nd->id)->count());
    }

    public function test_flujo_integral_agrupado_conserva_historicos_y_solo_reincorpora_aptos_aprobados(): void
    {
        $e = $this->escenarioUnidades();
        $historico = $this->pedidoEnvasado($e, 'entregado');
        $normal = $this->pedidoEnvasado($e, 'registrado');
        $originales = [];
        foreach ([$historico, $normal] as $control) {
            $control->refresh();
            $originales[$control->id] = [$control->getAttributes(), $control->detalles->map->getAttributes()->all()];
        }
        $p = $this->registrarUnidades($e, [19, 11, 6], $this->agrupacionesUnidades($e));
        $this->assertSame('72.00', $p->total);
        $this->assertSame(3, $p->detalles()->count());
        $this->post(route('pedidos.procesar', $p), ['accion' => 'confirmar'])->assertSessionHasNoErrors();
        $lista = $this->get(route('cumplimiento.lista-preparacion'))->assertOk();
        $filas = $lista->viewData('filas')->filter(fn (array $fila): bool => array_intersect(array_column($fila['presentaciones'], 'id'), array_column($e['skus'], 'id')) !== []);
        $this->assertSame(36, $filas->sum('total_unidades'));
        $this->assertSame([19, 11, 6], $filas->flatMap(fn (array $fila): array => array_values($fila['presentaciones']))->sortBy('id')->pluck('solicitadas')->values()->all());
        $this->post(route('cumplimiento.iniciar-preparacion', $p))->assertSessionHasNoErrors();
        $p->refresh();
        foreach ($p->detalles()->get() as $d) {
            $this->post(route('pedidos.unidades.preparar', $p), ['version_pedido' => $p->version, 'pedido_detalle_id' => $d->id, 'cantidad' => $d->cantidad, 'motivo' => 'Unidades verificadas físicamente para el flujo integral.'])->assertSessionHasNoErrors();
            $p->refresh();
        }
        $this->post(route('cumplimiento.finalizar-preparacion', $p))->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame([100, 100, 100], array_map(fn ($sku): int => (int) Inventario::where('presentacion_producto_id', $sku->id)->value('cantidad_actual'), $e['skus']));
        $salida = $this->salida($p);
        $this->post(route('pedidos.unidades.despachar', $p), $salida)->assertSessionHasNoErrors();
        $this->post(route('pedidos.unidades.despachar', $p), $salida)->assertSessionHasNoErrors();
        $stock = fn (): array => array_map(fn ($sku): int => (int) Inventario::where('presentacion_producto_id', $sku->id)->value('cantidad_actual'), $e['skus']);
        foreach ($e['skus'] as $i => $sku) {
            $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $sku->id, 'cantidad_actual' => [81, 89, 94][$i]]);
        }
        $fisicas = app(ControlEntregaPedido::class)->resumen($p->fresh())['lineas'];
        $resultados = [];
        $retornos = [];
        foreach ($fisicas as $f) {
            $rechazo = $f['presentacion_id'] === $e['skus'][0]->id ? 2 : ($f['presentacion_id'] === $e['skus'][1]->id ? 1 : 0);
            $resultados[] = ['pedido_detalle_id' => $f['id'], 'aceptadas' => $f['despachadas'] - $rechazo, 'rechazadas' => $rechazo, 'motivo' => 'El cliente informa un rechazo que requiere revisión comercial.'];
            if ($rechazo > 0) {
                $retornos[] = ['pedido_detalle_id' => $f['id'], 'aptas' => 1, 'danadas' => $rechazo - 1, 'en_revision' => 0, 'no_recuperadas' => 0];
            }
        }
        $this->post(route('cumplimiento.resultado-entrega', $p), ['version' => $this->versionEntrega($p), 'detalles' => $resultados])->assertSessionHasNoErrors();
        $resumen = app(ControlEntregaPedido::class)->resumen($p->fresh())['lineas'];
        $this->assertSame('entrega_parcial', $p->fresh()->estado);
        $this->assertSame(33, array_sum(array_column($resumen, 'aceptadas')));
        $this->assertSame(3, array_sum(array_column($resumen, 'rechazadas')));
        foreach ($e['skus'] as $i => $sku) {
            $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $sku->id, 'cantidad_actual' => [81, 89, 94][$i]]);
        }
        $this->post(route('cumplimiento.retornos', $p), ['version' => $this->versionEntrega($p), 'observaciones' => 'Recepción física de dos unidades aptas y una dañada.', 'detalles' => $retornos])->assertSessionHasNoErrors();
        foreach ($e['skus'] as $i => $sku) {
            $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $sku->id, 'cantidad_actual' => [81, 89, 94][$i]]);
        }
        foreach (DB::table('retorno_pedido_detalles')->whereIn('pedido_detalle_id', array_column($retornos, 'pedido_detalle_id'))->get() as $retorno) {
            $this->post(route('cumplimiento.aprobar-retorno', $p), ['version' => $this->versionEntrega($p), 'retorno_detalle_id' => $retorno->id, 'cantidad' => 1, 'motivo' => 'Se comprueba la recepción física y aptitud de esta unidad.'])->assertSessionHasNoErrors();
        }
        $this->assertSame([82, 90, 94], $stock());
        $this->assertSame('72.00', $p->fresh()->total);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        foreach ($originales as $id => $original) {
            $control = Pedido::findOrFail($id);
            $this->assertSame($original, [$control->getAttributes(), $control->detalles->map->getAttributes()->all()]);
        }
    }

    public function test_historial_logistico_inmutable_tambien_se_protege_en_mysql(): void
    {
        $e = $this->escenarioUnidades();
        $p = $this->registrarUnidades($e, [7, 11, 6], $this->agrupacionesUnidades($e));
        $grupo = DB::table('pedido_agrupaciones')->where('pedido_id', $p->id)->first();
        $this->expectException(QueryException::class);
        DB::table('pedido_agrupacion_detalles')->where('agrupacion_id', $grupo->id)->update(['cantidad' => 1]);
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
        app()->forgetInstance(LecturaPedido::class);
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
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--path' => [database_path('migrations/2026_10_30_002816_create_pedido_referencias_fisicas_table.php')], '--realpath' => true, '--force' => true, '--no-interaction' => true]));
        $this->assertSame(48, DB::table('migrations')->count());
        $this->assertSame(0, DB::table('pedido_referencias_fisicas')->count());
        foreach ($antes as $tabla => [$columnas, $filas]) {
            $this->assertSame($filas, DB::table($tabla)->select($columnas)->get()->map(fn ($r): string => json_encode($r))->sort()->values()->all(), $tabla.' cambió registros históricos.');
        }
        $this->assertSame('normal', DB::table('pedido_detalles')->where('pedido_id', $pedido->id)->value('tipo_linea'));
        $this->assertSame(0, DB::table('pedido_paquetes_surtidos')->count());
        $this->assertNull(DB::table('pedidos')->where('id', $pedido->id)->value('modelo_comercial'));
        $this->assertSame(0, (int) DB::table('pedido_detalles')->where('pedido_id', $pedido->id)->value('cantidad_espera'));
        foreach (['pedido_versiones', 'pedido_agrupaciones', 'pedido_agrupacion_detalles', 'pedido_preparaciones', 'pedido_despachos', 'pedido_despacho_lineas', 'pedido_despacho_agrupaciones'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), $tabla.' no debe generar datos retroactivos.');
        }
        $this->assertTrue(app(ControlEntregaPedido::class)->resumen($pedido->fresh())['historico_sin_desglose']);
    }
}
