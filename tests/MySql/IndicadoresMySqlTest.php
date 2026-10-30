<?php

namespace Tests\MySql;

use App\Models\PedidoDetalle;
use App\Services\ControlEntregaPedido;
use App\Services\CorregirEnvasado;
use App\Services\ExportarIndicadoresCsv;
use App\Services\FilasIndicadores;
use App\Services\FiltrosIndicadores;
use App\Services\PedidosIndicadores;
use App\Services\RegistrarEnvasado;
use App\Services\ResumenIndicadores;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\VentaUnidadesEscenario;

class IndicadoresMySqlTest extends PruebaEntregaMySql
{
    use VentaUnidadesEscenario;

    public function test_todos_los_reportes_son_lecturas_compatibles_con_mysql_y_componentes_reales(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00', 'America/Lima'));
        $e = $this->escenarioUnidades();
        $p = $this->registrarUnidades($e, [7, 11, 6], $this->agrupacionesUnidades($e));
        $this->despacharUnidades($p, $e);
        $fisica = collect(app(ControlEntregaPedido::class)->resumen($p->fresh())['lineas'])->firstWhere('presentacion_id', $e['skus'][0]->id);
        $d = PedidoDetalle::findOrFail($fisica['id']);
        $version = fn (): int => app(ControlEntregaPedido::class)->resumen($p->fresh())['version'];
        $this->post(route('cumplimiento.resultado-entrega', $p), ['version' => $version(), 'detalles' => [['pedido_detalle_id' => $d->id, 'aceptadas' => 3, 'rechazadas' => 2, 'motivo' => 'Dos unidades rechazadas para esta prueba aislada.']]])->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.retornos', $p), ['version' => $version(), 'observaciones' => 'Recepción física de dos unidades para revisión.', 'detalles' => [['pedido_detalle_id' => $d->id, 'aptas' => 1, 'danadas' => 1, 'en_revision' => 0, 'no_recuperadas' => 0]]])->assertSessionHasNoErrors();
        $rd = DB::table('retorno_pedido_detalles')->where('pedido_detalle_id', $d->id)->sole()->id;
        $this->post(route('cumplimiento.aprobar-retorno', $p), ['version' => $version(), 'retorno_detalle_id' => $rd, 'cantidad' => 1, 'motivo' => 'Unidad apta aprobada en la base de prueba.'])->assertSessionHasNoErrors();
        $antes = DB::table('movimientos_inventario')->count();

        $esperados = ['comercial' => 3, 'composiciones' => 6, 'situacion_entregas' => 3, 'preparacion' => 0, 'fisicas' => 0, 'pendientes' => 0, 'entregas' => 1, 'retornos' => 1, 'aprobaciones' => 1, 'envasados' => 0, 'materiales' => 0, 'materiales_resumen' => 0, 'movimientos' => 4, 'saldos_periodo' => 3, 'inventario' => 4];
        foreach (array_keys(FilasIndicadores::REPORTES) as $reporte) {
            $f = FiltrosIndicadores::validar(['reporte' => $reporte, 'economico' => 1]);
            $filas = iterator_to_array(app(FilasIndicadores::class)->filas($f));
            $this->assertCount($esperados[$reporte], $filas, $reporte);
        }

        $f = FiltrosIndicadores::validar(['reporte' => 'entregas', 'economico' => 1]);
        $entregas = iterator_to_array(app(FilasIndicadores::class)->filas($f));
        $this->assertSame(600, $entregas[0]['aceptado_centimos']);
        $this->assertSame(400, $entregas[0]['rechazado_centimos']);
        $this->assertSame($antes, DB::table('movimientos_inventario')->count());
        $this->assertSame(94, (int) DB::table('inventarios')->where('presentacion_producto_id', $e['skus'][0]->id)->value('cantidad_actual'));
    }

    public static function volumenes(): array
    {
        return ['pequeño' => [3], 'grande' => [1500]];
    }

    #[DataProvider('volumenes')]
    public function test_paginacion_exportacion_y_planes_sin_n_mas_uno_ni_memoria_de_todo_el_csv(int $cantidad): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00', 'America/Lima'));
        $e = $this->escenarioEnvasado();
        $p = $this->pedidoEnvasado($e);
        $plantilla = (array) DB::table('pedidos')->where('id', $p->id)->first();
        unset($plantilla['id']);
        for ($i = 1; $i < $cantidad; $i++) {
            $id = DB::table('pedidos')->insertGetId($plantilla);
            $d = (array) DB::table('pedido_detalles')->where('pedido_id', $p->id)->first();
            unset($d['id']);
            $d['pedido_id'] = $id;
            DB::table('pedido_detalles')->insert($d);
        }
        $f = FiltrosIndicadores::validar(['reporte' => 'comercial', 'economico' => 1]);
        $consultas = 0;
        $sqlMs = 0.0;
        DB::listen(function ($q) use (&$consultas, &$sqlMs): void {
            $consultas++;
            $sqlMs += $q->time;
        });
        memory_reset_peak_usage();
        $memoria = memory_get_usage(true);
        $inicio = hrtime(true);

        $pantalla = app(ResumenIndicadores::class)->pagina($f);
        $consultaPantalla = $consultas;
        $msPantalla = (hrtime(true) - $inicio) / 1e6;
        $consultas = 0;
        $inicio = hrtime(true);
        $salida = fopen('php://temp/maxmemory:1048576', 'w+b');
        app(ExportarIndicadoresCsv::class)->escribir($salida, $f, '2026-10-24 12:00:00');
        $bytes = ftell($salida);
        $msCsv = (hrtime(true) - $inicio) / 1e6;
        $consultaCsv = $consultas;
        $sqlRegistrado = $sqlMs;
        $pico = memory_get_peak_usage(true) - $memoria;
        rewind($salida);
        $filasCsv = 0;
        while (fgetcsv($salida, null, ';', '"', '') !== false) {
            $filasCsv++;
        }fclose($salida);

        $this->assertSame($cantidad, $pantalla['cantidad']);
        $this->assertSame($cantidad * 1000, $pantalla['totales']['importe_centimos']);
        $this->assertSame($cantidad + 1, $filasCsv);
        $this->assertLessThanOrEqual((int) ceil($cantidad / 100) + 4, $consultaPantalla);
        $this->assertLessThanOrEqual((int) ceil($cantidad / 100) + 1, $consultaCsv);
        $this->assertLessThan(32 * 1024 * 1024, $pico);
        $q = app(PedidosIndicadores::class)->consultaComercial($f)->limit(100);
        $plan = DB::select('EXPLAIN '.$q->toSql(), $q->getBindings());
        $this->assertNotEmpty($plan);
        fwrite(STDERR, 'INDICADORES_METRICAS '.json_encode(['filas' => $cantidad, 'consultas_pantalla' => $consultaPantalla, 'consultas_csv' => $consultaCsv, 'pantalla_ms' => round($msPantalla, 2), 'csv_ms' => round($msCsv, 2), 'sql_ms' => round($sqlRegistrado, 2), 'pico_adicional_bytes' => $pico, 'csv_bytes' => $bytes, 'plan' => $plan], JSON_UNESCAPED_UNICODE).PHP_EOL);
    }

    public function test_materiales_saldos_y_ventanas_se_ejecutan_en_mysql_estricto(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00', 'America/Lima'));
        $e = $this->escenarioEnvasado(100, 0);
        $this->actingAs($e['usuario']);
        $envasado = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 10], $e['usuario']);
        app(CorregirEnvasado::class)->ejecutar($envasado, ['clasificacion' => 'error_administrativo', 'cantidad' => 2, 'revertidas_previas' => 0, 'motivo' => 'Dos unidades registradas por error en esta prueba.', 'devoluciones' => [$envasado->consumos->sole()->id => 2]], $e['usuario']);

        $materiales = iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar(['reporte' => 'materiales_resumen'])));
        $saldos = iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar(['reporte' => 'saldos_periodo'])));

        $this->assertSame(8.0, (float) $materiales[0]['salida_neta']);
        $this->assertCount(2, $saldos);
        $this->assertSame(['Concordante', 'Concordante'], array_column($saldos, 'conciliacion'));
    }

    public function test_sku_historico_del_envasado_filtra_en_mysql_aunque_cambie_el_catalogo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00', 'America/Lima'));
        $e = $this->escenarioEnvasado(100, 0);
        $datos = ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0];
        $antiguo = app(RegistrarEnvasado::class)->ejecutar($datos + ['cantidad' => 10], $e['usuario']);
        $codigo = $e['presentacion']->codigo_sku;
        $e['presentacion']->update(['codigo_sku' => 'NUEVO-HISTORICO-MYSQL']);
        app(CorregirEnvasado::class)->ejecutar($antiguo, ['clasificacion' => 'error_administrativo', 'cantidad' => 2, 'revertidas_previas' => 0, 'motivo' => 'Corrección aislada posterior al cambio de SKU.', 'devoluciones' => [$antiguo->consumos->sole()->id => 2]], $e['usuario']);
        app(RegistrarEnvasado::class)->ejecutar($datos + ['cantidad' => 5], $e['usuario']);

        foreach (['envasados' => 'efecto_neto', 'materiales' => 'salida_neta', 'materiales_resumen' => 'salida_neta'] as $reporte => $metrica) {
            $viejas = iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar(['reporte' => $reporte, 'sku' => $codigo])));
            $nuevas = iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar(['reporte' => $reporte, 'sku' => 'NUEVO-HISTORICO-MYSQL'])));
            $this->assertSame(8.0, (float) array_sum(array_column($viejas, $metrica)), $reporte);
            $this->assertSame(5.0, (float) array_sum(array_column($nuevas, $metrica)), $reporte);
        }
        $this->assertSame($codigo, $antiguo->fresh()->presentacion_historica['sku']);
        $this->assertSame(13, (int) DB::table('inventarios')->where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
    }

    public static function cadenasDeMovimientos(): array
    {
        return [
            'saltos compensados' => [[['2026-10-20', 10, 8], ['2026-10-20', 5, 7], ['2026-10-20', 10, 10]], 'Revisar continuidad del historial'],
            'cadena continua' => [[['2026-10-20', 10, 8], ['2026-10-20', 8, 11], ['2026-10-20', 11, 6]], 'Concordante'],
            'predecesor fuera del período' => [[['2026-09-30', 10, 8], ['2026-10-20', 5, 7], ['2026-10-20', 7, 9]], 'Revisar continuidad del historial'],
            'balance agregado no verificable' => [[['2026-10-01', 10, 8], ['2026-11-01', 8, 11], ['2026-10-20', 11, 12]], 'Revisar continuidad del historial'],
        ];
    }

    #[DataProvider('cadenasDeMovimientos')]
    public function test_concordancia_mysql_comprueba_cada_enlace_y_el_balance_del_periodo(array $movimientos, string $etiqueta): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20', 'America/Lima'));
        $e = $this->escenarioEnvasado(100, 10);
        foreach ($movimientos as [$fecha, $antes, $despues]) {
            DB::table('movimientos_inventario')->insert(['presentacion_producto_id' => $e['presentacion']->id, 'usuario_id' => $e['usuario']->id, 'tipo' => 'ajuste', 'cantidad' => $despues, 'stock_anterior' => $antes, 'stock_nuevo' => $despues, 'motivo' => 'Historial ficticio para validar continuidad en MySQL aislado.', 'origen_tipo' => 'manual', 'origen_id' => (string) Str::uuid(), 'created_at' => $fecha.' 12:00:00', 'updated_at' => $fecha.' 12:00:00']);
        }
        $originales = DB::table('movimientos_inventario')->orderBy('id')->get()->toJson();

        $f = FiltrosIndicadores::validar(['reporte' => 'saldos_periodo', 'desde' => '2026-10-01', 'hasta' => '2026-10-31']);
        $saldos = iterator_to_array(app(FilasIndicadores::class)->filas($f));

        $this->assertCount(1, $saldos);
        $this->assertSame($etiqueta, $saldos[0]['conciliacion']);
        $this->assertSame($originales, DB::table('movimientos_inventario')->orderBy('id')->get()->toJson());
        $this->assertSame(10, (int) DB::table('inventarios')->where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
    }

    public function test_preparacion_conserva_identidad_del_inventario_ante_codigos_historicos_distintos(): void
    {
        $e = $this->escenarioEnvasado(100, 6);
        $p = $this->pedidoEnvasado($e);
        $p->detalles()->update(['codigo_sku' => 'ANTERIOR-MYSQL']);
        $this->pedidoEnvasado($e);

        $filas = iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar(['reporte' => 'preparacion'])));

        $this->assertCount(1, $filas);
        $this->assertSame(10, $filas[0]['solicitadas']);
        $this->assertSame(4, $filas[0]['faltante_conocido']);
        $this->assertSame($e['presentacion']->codigo_sku, $filas[0]['sku']);
    }

    public function test_desglose_de_muchas_agrupaciones_tiene_orden_unico_sin_duplicar_demanda(): void
    {
        $e = $this->escenarioUnidades();
        $grupos = array_fill(0, 52, $this->agrupacionesUnidades($e)[0]);
        $p = $this->registrarUnidades($e, [260, 208, 156], $grupos);
        $p->update(['estado' => 'confirmado']);
        $f = FiltrosIndicadores::validar(['reporte' => 'composiciones']);
        $filas = iterator_to_array(app(FilasIndicadores::class)->filas($f));
        $ids = array_column($filas, 'componente_id');
        $esperados = DB::table('pedido_agrupacion_detalles')->orderBy('id')->pluck('id')->all();
        $this->assertSame($esperados, $ids);
        $this->assertCount(156, $filas);
        $this->assertCount(52, array_unique(array_column($filas, 'paquete_id')));
        $this->assertSame(624, array_sum(array_column($filas, 'unidades_fisicas')));
        $demanda = iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar(['reporte' => 'fisicas'])));
        $this->assertCount(3, $demanda);
        $this->assertSame(624, array_sum(array_column($demanda, 'unidades_fisicas')));
    }

    public function test_exportacion_por_lotes_mantiene_una_fotografia_ante_una_escritura_concurrente(): void
    {
        $anterior = DB::getDefaultConnection();
        $c = EntornoMySql::proteger();
        $c['database'] .= '_lectura_'.strtolower(Str::random(4));
        if (strlen($c['database']) > 64) {
            throw new \RuntimeException('Nombre aislado demasiado largo.');
        }
        $servidor = new \PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4', $c['username'], $c['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $servidor->exec('CREATE DATABASE `'.$c['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        config(['database.connections.indicadores_snapshot' => $c]);
        try {
            Artisan::call('migrate', ['--database' => 'indicadores_snapshot', '--force' => true, '--no-interaction' => true]);
            DB::setDefaultConnection('indicadores_snapshot');
            $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00', 'America/Lima'));
            $e = $this->escenarioEnvasado();
            $ultimo = null;
            for ($i = 0; $i < 105; $i++) {
                $ultimo = $this->pedidoEnvasado($e);
            }
            $escritor = new \PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['database'].';charset=utf8mb4', $c['username'], $c['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $cambio = false;
            $id = $ultimo->id;
            DB::listen(function ($q) use (&$cambio, $escritor, $id): void {
                if (! $cambio && $q->connectionName === 'indicadores_snapshot' && str_contains($q->sql, 'pedido_detalles') && str_contains($q->sql, 'order by')) {
                    $cambio = true;
                    $escritor->beginTransaction();
                    $escritor->exec('UPDATE pedido_detalles SET precio_unitario=3,importe=15 WHERE pedido_id='.(int) $id);
                    $escritor->exec('UPDATE pedidos SET total=15 WHERE id='.(int) $id);
                    $escritor->commit();
                }
            });
            $salida = fopen('php://temp', 'w+b');

            DB::transaction(fn () => app(ExportarIndicadoresCsv::class)->escribir($salida, FiltrosIndicadores::validar(['reporte' => 'comercial', 'economico' => 1]), '2026-10-24 12:00:00'));

            rewind($salida);
            $cabecera = fgetcsv($salida, null, ';', '"', '');
            $indice = array_search('importe_centimos', $cabecera, true);
            $total = 0;
            while (($fila = fgetcsv($salida, null, ';', '"', '')) !== false) {
                $total += (int) $fila[$indice];
            }fclose($salida);
            $this->assertTrue($cambio);
            $this->assertSame(105000, $total);
            $this->assertSame('15.00', DB::table('pedidos')->where('id', $id)->value('total'));
        } finally {
            DB::purge('indicadores_snapshot');
            DB::setDefaultConnection($anterior);
        }
    }
}
