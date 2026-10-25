<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Services\ActividadIndicadores;
use App\Services\CorregirEnvasado;
use App\Services\FilasIndicadores;
use App\Services\FiltrosIndicadores;
use App\Services\GestionarSurtidos;
use App\Services\RegistrarEnvasado;
use App\Services\ResumenIndicadores;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\SurtidosEscenario;
use Tests\TestCase;

class IndicadoresOperacionesTest extends TestCase
{
    use RefreshDatabase, SurtidosEscenario;

    private function escenario(bool $surtido = false): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00', 'America/Lima'));
        $e = $this->escenarioSurtido();
        foreach (['cumplimiento.incidencias.revisar', 'cumplimiento.incidencias.resolver'] as $n) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $n], ['descripcion' => $n])->id);
        }
        $p = $this->pedidoEnvasado($e, $surtido ? 'confirmado' : 'listo_despacho');
        $d = $p->detalles()->firstOrFail();
        if ($surtido) {
            $d->update(['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'], 'modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'cantidad' => 2, 'precio_unitario' => 24, 'importe' => 48]);
            app(GestionarSurtidos::class)->guardar($d, $this->paquetesSurtidos($e), $e['usuario'], 'Composición ficticia para validar reportes de entregas.');
            $p->update(['total' => 48, 'estado' => 'listo_despacho']);
        }
        $this->post(route('cumplimiento.despachar', $p))->assertSessionHasNoErrors();
        $dc = $surtido ? DB::table('pedido_despacho_componentes')->orderBy('id')->first()->id : null;

        return $e + ['pedido' => $p->fresh(), 'detalle' => $d->fresh(), 'componente' => $dc];
    }

    private function filas(string $reporte, array $extra = []): array
    {
        return iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar($extra + ['reporte' => $reporte, 'economico' => 1, 'desde' => '2026-10-01', 'hasta' => '2026-10-31'])), false);
    }

    private function version(array $e): int
    {
        return (int) DB::table('incidencias_entrega')->where('pedido_id', $e['pedido']->id)->value('version');
    }

    private function resultado(array $e, int $a, int $r, bool $rectificar = false): void
    {
        $this->post(route($rectificar ? 'cumplimiento.rectificar-entrega' : 'cumplimiento.resultado-entrega', $e['pedido']), ['version' => $this->version($e), 'observaciones' => 'Resultado documentado para pruebas aisladas.', 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'despacho_componente_id' => $e['componente'], 'aceptadas' => $a, 'rechazadas' => $r, 'motivo' => 'Rechazo comunicado sin atribuir responsabilidades.']]])->assertSessionHasNoErrors();
    }

    public function test_rectificaciones_entre_periodos_conservan_signo_y_valoracion(): void
    {
        $e = $this->escenario();
        $this->resultado($e, 3, 2);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'America/Lima'));
        $this->resultado($e, 1, -1, true);

        $octubre = $this->filas('entregas');
        $noviembre = $this->filas('entregas', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']);

        $this->assertCount(1, $octubre);
        $this->assertSame(600, $octubre[0]['aceptado_centimos']);
        $this->assertSame(400, $octubre[0]['rechazado_centimos']);
        $this->assertSame(200, $noviembre[0]['aceptado_centimos']);
        $this->assertSame(-200, $noviembre[0]['rechazado_centimos']);
        $this->assertSame(-1, $noviembre[0]['rechazadas']);
        $actual = $this->filas('situacion_entregas');
        $this->assertSame(4, $actual[0]['aceptadas']);
        $this->assertSame(1, $actual[0]['rechazadas']);
        $this->assertSame(0, $actual[0]['pendientes']);
        $this->assertSame(800, $actual[0]['aceptado_centimos']);
        $this->assertSame(200, $actual[0]['rechazado_centimos']);
    }

    public function test_retornos_clasificaciones_y_aprobaciones_parciales_no_se_duplican(): void
    {
        $e = $this->escenario(true);
        $this->resultado($e, 1, 4);
        $this->post(route('cumplimiento.retornos', $e['pedido']), ['version' => $this->version($e), 'observaciones' => 'Recepción parcial documentada en la base aislada.', 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'despacho_componente_id' => $e['componente'], 'aptas' => 1, 'danadas' => 0, 'en_revision' => 2, 'no_recuperadas' => 1]]])->assertSessionHasNoErrors();
        $rd = DB::table('retorno_pedido_detalles')->first()->id;
        $this->post(route('cumplimiento.incidencia.clasificar', $e['pedido']), ['version' => $this->version($e), 'motivo' => 'Clasificación posterior de las unidades recibidas.', 'retorno_detalle_id' => $rd, 'aptas' => 1, 'danadas' => 1])->assertSessionHasNoErrors();
        foreach ([1, 1] as $cantidad) {
            $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), ['version' => $this->version($e), 'retorno_detalle_id' => $rd, 'cantidad' => $cantidad, 'motivo' => 'Reincorporación parcial verificada físicamente.'])->assertSessionHasNoErrors();
        }

        $retornos = $this->filas('retornos');
        $aprobaciones = $this->filas('aprobaciones');
        $actual = $this->filas('situacion_entregas');

        $this->assertCount(2, $retornos);
        $this->assertSame(3, array_sum(array_column($retornos, 'recibidas')));
        $this->assertSame(2, array_sum(array_column($retornos, 'aptas')));
        $this->assertSame(1, array_sum(array_column($retornos, 'danadas')));
        $this->assertSame(0, array_sum(array_column($retornos, 'en_revision')));
        $this->assertSame(2, array_sum(array_column($aprobaciones, 'reincorporadas')));
        $this->assertCount(2, $aprobaciones);
        $ref = collect($actual)->firstWhere('despacho_componente_id', $e['componente']);
        $this->assertSame(0, $ref['por_retornar']);
        $this->assertSame(0, $ref['por_aprobar']);
        $this->assertSame(95, (int) DB::table('inventarios')->where('presentacion_producto_id', $e['skus'][0]->id)->value('cantidad_actual'));
        $this->assertSame(5, DB::table('movimientos_inventario')->count());
    }

    public function test_reincorporacion_sin_movimiento_no_se_declara_verificada(): void
    {
        $e = $this->escenario();
        $this->resultado($e, 3, 2);
        $inc = DB::table('incidencias_entrega')->first()->id;
        $r = DB::table('retornos_pedido')->insertGetId(['incidencia_id' => $inc, 'usuario_id' => $e['usuario']->id, 'ocurrido_en' => now(), 'observaciones' => 'Registro histórico incompleto de prueba.', 'created_at' => now()]);
        $rd = DB::table('retorno_pedido_detalles')->insertGetId(['retorno_id' => $r, 'pedido_detalle_id' => $e['detalle']->id, 'aptas' => 1]);
        DB::table('incidencia_entrega_eventos')->insert(['incidencia_id' => $inc, 'usuario_id' => $e['usuario']->id, 'tipo' => 'retorno_aprobado', 'motivo' => 'Evidencia histórica incompleta simulada.', 'datos' => json_encode(['retorno_detalle_id' => $rd, 'cantidad' => 1]), 'created_at' => now()]);

        $filas = $this->filas('aprobaciones');

        $this->assertNull($filas[0]['reincorporadas']);
        $this->assertStringStartsWith('No:', $filas[0]['verificable']);
    }

    public function test_historicos_entregados_sin_desglose_no_se_convierten_en_aceptados(): void
    {
        $e = $this->escenarioSurtido();
        $this->pedidoEnvasado($e, 'entregado');

        $filas = $this->filas('situacion_entregas');

        $this->assertNull($filas[0]['aceptadas']);
        $this->assertNull($filas[0]['despachadas']);
        $this->assertNull($filas[0]['aceptado_centimos']);
        $this->assertDatabaseCount('incidencias_entrega', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_prorrateo_conserva_centimos_y_no_redondea_independientemente(): void
    {
        $s = app(ActividadIndicadores::class);
        $foto = ['importe_centimos' => 1000, 'despachadas' => 3];

        $primero = $s->valorar($foto, 1, 1);
        $segundo = $s->valorar($foto, 1, 2);

        $this->assertSame([333, 333], $primero);
        $this->assertSame([333, 667], $segundo);
        $this->assertSame(334, $segundo[1] - $primero[1]);
    }

    public function test_envasado_y_correccion_en_otro_periodo_separan_actividad_y_neto_actual(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00', 'America/Lima'));
        $e = $this->escenarioEnvasado(100, 0);
        $this->actingAs($e['usuario']);
        $envasado = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 10], $e['usuario']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'America/Lima'));
        app(CorregirEnvasado::class)->ejecutar($envasado, ['clasificacion' => 'perdida_fisica', 'cantidad' => 3, 'revertidas_previas' => 0, 'motivo' => 'Tres unidades dañadas con un envase recuperado.', 'devoluciones' => [$envasado->consumos->sole()->id => 1]], $e['usuario']);

        $octubre = $this->filas('envasados');
        $noviembre = $this->filas('envasados', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']);
        $materiales = $this->filas('materiales_resumen', ['hasta' => '2026-11-30']);

        $this->assertSame(10, $octubre[0]['efecto_neto']);
        $this->assertSame(7, $octubre[0]['neto_actual_envasado']);
        $this->assertSame(-3, $noviembre[0]['efecto_neto']);
        $this->assertSame(10.0, (float) $materiales[0]['consumo_bruto']);
        $this->assertSame(1.0, (float) $materiales[0]['devuelto']);
        $this->assertSame(2.0, (float) $materiales[0]['perdido']);
        $this->assertSame(9.0, (float) $materiales[0]['salida_neta']);
        $this->assertSame(7, (int) DB::table('inventarios')->value('cantidad_actual'));
    }

    public function test_ajuste_usa_diferencia_de_saldos_y_conserva_saldo_inicial(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20', 'America/Lima'));
        $e = $this->escenarioEnvasado(20, 10);
        $this->actingAs($e['usuario']);
        $e['usuario']->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => 'inventario.ajustar'], ['descripcion' => 'Ajustar'])->id);
        $e['usuario']->unsetRelation('role');
        $this->post(route('inventario.movimientos.store'), ['presentacion_producto_id' => $e['presentacion']->id, 'tipo' => 'ajuste', 'cantidad' => 4, 'motivo' => 'Conteo físico de prueba aislada.'])->assertSessionHasNoErrors();

        $m = $this->filas('movimientos');
        $s = $this->filas('saldos_periodo');

        $this->assertSame(-6.0, (float) $m[0]['variacion']);
        $this->assertSame(10.0, (float) $s[0]['saldo_inicial']);
        $this->assertSame(4.0, (float) $s[0]['saldo_final']);
        $this->assertSame('Concordante', $s[0]['conciliacion']);
    }

    public function test_limites_del_periodo_excluyen_el_dia_siguiente_y_admiten_el_primero(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20', 'America/Lima'));
        $e = $this->escenarioEnvasado();
        $p = $this->pedidoEnvasado($e);
        $p->update(['fecha_pedido' => '2026-10-01']);
        $otro = $this->pedidoEnvasado($e);
        $otro->update(['fecha_pedido' => '2026-11-01']);

        $f = $this->filas('comercial');

        $this->assertCount(1, $f);
        $this->assertSame($p->id, $f[0]['pedido_id']);
    }

    public function test_reincorporacion_acumulada_sin_movimiento_se_muestra_no_disponible(): void
    {
        $e = $this->escenario();
        $this->resultado($e, 3, 2);
        $inc = DB::table('incidencias_entrega')->first()->id;
        $r = DB::table('retornos_pedido')->insertGetId(['incidencia_id' => $inc, 'usuario_id' => $e['usuario']->id, 'ocurrido_en' => now(), 'observaciones' => 'Histórico incompleto simulado exclusivamente en pruebas.', 'created_at' => now()]);
        DB::table('retorno_pedido_detalles')->insert(['retorno_id' => $r, 'pedido_detalle_id' => $e['detalle']->id, 'aptas' => 1, 'reincorporadas' => 1]);

        $situacion = $this->filas('situacion_entregas');

        $this->assertNull($situacion[0]['reincorporadas']);
        $this->assertNull($situacion[0]['por_aprobar']);
        $this->assertStringContainsString('sin movimiento', $situacion[0]['verificable']);
        $resumen = app(ResumenIndicadores::class)->pagina(FiltrosIndicadores::validar(['reporte' => 'situacion_entregas']));
        $this->assertNull($resumen['totales']['reincorporadas']);
        $this->assertSame(1, $resumen['noDisponibles']['reincorporadas']);
    }

    public function test_sku_historico_sigue_filtrando_entregas_si_cambia_el_codigo_del_catalogo(): void
    {
        $e = $this->escenario(true);
        $this->resultado($e, 3, 2);
        $codigo = $e['skus'][0]->codigo_sku;
        $e['skus'][0]->update(['codigo_sku' => 'CODIGO-CAMBIADO']);

        $entregas = $this->filas('entregas', ['sku' => $codigo]);

        $this->assertCount(1, $entregas);
        $this->assertSame($codigo, $entregas[0]['sku']);
        $this->assertSame(600, $entregas[0]['aceptado_centimos']);
    }

    public function test_envasados_y_consumos_filtran_su_sku_historico_en_pantalla_y_csv(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20', 'America/Lima'));
        $e = $this->escenarioEnvasado(100, 0);
        $this->actingAs($e['usuario']);
        foreach (['indicadores.ver', 'indicadores.economicos.ver', 'indicadores.exportar'] as $nombre) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }
        $e['usuario']->unsetRelation('role');
        $datos = ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0];
        $anterior = app(RegistrarEnvasado::class)->ejecutar($datos + ['cantidad' => 10], $e['usuario']);
        $foto = $anterior->getRawOriginal('presentacion_historica');
        $codigo = $e['presentacion']->codigo_sku;
        $e['presentacion']->update(['codigo_sku' => 'SKU-ACTUAL-REGRESION']);
        app(CorregirEnvasado::class)->ejecutar($anterior, ['clasificacion' => 'error_administrativo', 'cantidad' => 2, 'revertidas_previas' => 0, 'motivo' => 'Corrección ficticia posterior al cambio de código.', 'devoluciones' => [$anterior->consumos->sole()->id => 2]], $e['usuario']);
        app(RegistrarEnvasado::class)->ejecutar($datos + ['cantidad' => 5], $e['usuario']);

        foreach (['envasados' => 'efecto_neto', 'materiales' => 'salida_neta', 'materiales_resumen' => 'salida_neta'] as $reporte => $metrica) {
            $filtro = ['reporte' => $reporte, 'sku' => $codigo, 'economico' => 1];
            $viejas = $this->filas($reporte, ['sku' => $codigo]);
            $nuevas = $this->filas($reporte, ['sku' => 'SKU-ACTUAL-REGRESION']);
            $this->assertCount($reporte === 'materiales_resumen' ? 1 : 2, $viejas, $reporte);
            $this->assertSame(8.0, (float) array_sum(array_column($viejas, $metrica)), $reporte);
            $this->assertCount(1, $nuevas, $reporte);
            $this->assertSame(5.0, (float) array_sum(array_column($nuevas, $metrica)), $reporte);
            $this->get(route('indicadores.index', $filtro))->assertOk()->assertViewHas('resultado', fn ($r): bool => $r['cantidad'] === count($viejas));
            $csv = $this->get(route('indicadores.exportar', $filtro))->assertOk()->streamedContent();
            $salida = fopen('php://temp', 'w+b');
            fwrite($salida, substr($csv, 3));
            rewind($salida);
            $cabecera = fgetcsv($salida, null, ';', '"', '');
            $filas = [];
            while (($fila = fgetcsv($salida, null, ';', '"', '')) !== false) {
                $filas[] = array_combine($cabecera, $fila);
            }
            fclose($salida);
            $this->assertCount(count($viejas), $filas);
            $this->assertSame(8.0, (float) array_sum(array_column($filas, $metrica)));
        }
        $this->assertSame($foto, $anterior->fresh()->getRawOriginal('presentacion_historica'));
        $this->assertSame([], $this->filas('inventario', ['sku' => $codigo]));
        $this->assertSame(13, (int) $this->filas('inventario', ['sku' => 'SKU-ACTUAL-REGRESION'])[0]['stock_actual']);
        $this->assertSame(87.0, (float) $e['insumo']->fresh()->cantidad_actual);
    }
}
