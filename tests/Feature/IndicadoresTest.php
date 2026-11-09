<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Services\ExportarIndicadoresCsv;
use App\Services\FilasIndicadores;
use App\Services\FiltrosIndicadores;
use App\Services\GestionarPedidoUnidades;
use Carbon\CarbonImmutable;
use Database\Seeders\IndicadoresPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\VentaUnidadesEscenario;

class IndicadoresTest extends TestCase
{
    use RefreshDatabase, VentaUnidadesEscenario;

    private function escenario(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 10:00:00', 'America/Lima'));
        $e = $this->escenarioUnidades();
        foreach (['indicadores.ver', 'indicadores.economicos.ver', 'indicadores.exportar'] as $n) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $n], ['descripcion' => $n])->id);
        }
        $e['usuario']->unsetRelation('role');

        return $e;
    }

    private function pedidoAgrupado(array $e): Pedido
    {
        $p = $this->registrarUnidades($e, [19, 11, 6], $this->agrupacionesUnidades($e));
        $p->update(['estado' => 'confirmado']);

        return $p->fresh();
    }

    private function filas(string $reporte, array $extra = []): array
    {
        return iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar($extra + ['reporte' => $reporte, 'economico' => 1])), false);
    }

    public function test_surtidos_normales_y_precios_no_se_multiplican_por_componentes(): void
    {
        $e = $this->escenario();
        $p = $this->pedidoAgrupado($e);

        $r = $this->get(route('indicadores.index', ['economico' => 1]));

        $r->assertOk();
        $resumen = $r->viewData('resultado');
        $this->assertSame(7200, $resumen['totales']['importe_centimos']);
        $this->assertSame(1, (int) $resumen['comercial']['pedidos_registrados']);
        $this->assertSame(36, (int) $resumen['comercial']['unidades_comerciales']);
        $this->assertSame(0, (int) $resumen['comercial']['lineas_sin_sku_verificable']);
        $this->assertCount(6, $this->filas('composiciones'));
        $fisicas = collect($this->filas('fisicas'))->groupBy('sku')->map(fn ($g): int => $g->sum('unidades_fisicas'));
        $this->assertSame(19, $fisicas[$e['skus'][0]->codigo_sku]);
        $this->assertSame(11, $fisicas[$e['skus'][1]->codigo_sku]);
        $this->assertSame(6, $fisicas[$e['skus'][2]->codigo_sku]);
        $this->assertSame('72.00', $p->fresh()->total);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_un_cambio_de_codigo_no_permite_usar_dos_veces_el_stock_de_la_misma_presentacion(): void
    {
        $e = $this->escenario();
        $primero = $this->pedidoEnvasado($e);
        $primero->detalles()->update(['codigo_sku' => 'CODIGO-ANTERIOR']);
        $this->pedidoEnvasado($e);
        DB::table('inventarios')->where('presentacion_producto_id', $e['presentacion']->id)->update(['cantidad_actual' => 6]);

        $preparacion = $this->filas('preparacion');

        $this->assertCount(1, $preparacion);
        $this->assertSame(10, $preparacion[0]['solicitadas']);
        $this->assertSame(6, $preparacion[0]['stock_actual']);
        $this->assertSame(4, $preparacion[0]['faltante_conocido']);
        $this->assertSame($e['presentacion']->codigo_sku, $preparacion[0]['sku']);
        $this->assertCount(2, $this->filas('fisicas'));
    }

    public function test_espera_no_inventa_demanda_y_no_suma_indicadores_superpuestos(): void
    {
        $e = $this->escenario();
        $entrada = $this->entradaUnidades($e, [19, 11, 6]);
        $entrada['detalles'][0]['cantidad_espera'] = 8;
        $entrada['motivo'] = 'Se dejan ocho unidades en espera por revisión operativa.';
        $p = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);
        $p->update(['estado' => 'confirmado']);
        $pendientes = $this->filas('pendientes');
        $this->assertSame(8, $pendientes[0]['unidades_en_espera']);
        $this->assertSame('unidades_en_espera', $pendientes[0]['tipo_pendiente']);
        $this->assertSame(36, array_sum(array_column($this->filas('fisicas'), 'unidades_fisicas')));
        $this->assertSame(28, array_sum(array_column($this->filas('preparacion'), 'unidades_activas')));
        $this->assertStringContainsString('espera', $this->filas('preparacion')[0]['cobertura']);
    }

    public function test_solo_version_vigente_suma_y_el_historial_sigue_exportable(): void
    {
        $e = $this->escenario();
        $p = $this->pedidoAgrupado($e);
        $grupos = $this->agrupacionesUnidades($e);
        $grupos[0] = $grupos[1];
        $entrada = $this->entradaUnidades($e, [16, 14, 6], $grupos);
        $entrada['cliente_id'] = $p->cliente_id;
        foreach ($p->detalles()->get() as $i => $d) {
            $entrada['detalles'][$i]['id'] = $d->id;
        }
        $entrada['_version'] = $p->version;
        $entrada['motivo'] = 'Cambio auditado de unidades y agrupación sin nuevas salidas.';
        app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario'], $p);
        $actuales = $this->filas('composiciones');
        $historicas = $this->filas('composiciones', ['historial' => 1]);
        $this->assertCount(6, $actuales);
        $this->assertCount(12, $historicas);
        $this->assertSame(24, array_sum(array_column($actuales, 'unidades_fisicas')));
        $this->assertCount(6, array_filter($historicas, fn ($f): bool => $f['version_vigente'] === 'No'));
        $this->assertSame(36, array_sum(array_column($this->filas('fisicas'), 'unidades_fisicas')));
    }

    public function test_cancelados_se_cuentan_aparte_y_no_generan_preparacion(): void
    {
        $e = $this->escenario();
        $p = $this->pedidoAgrupado($e);
        $p->update(['estado' => 'cancelado']);

        $r = $this->get(route('indicadores.index', ['economico' => 1]));

        $r->assertOk();
        $c = $r->viewData('resultado')['comercial'];
        $this->assertSame(1, (int) $c['pedidos_cancelados']);
        $this->assertSame(0, $c['importe_activo_centimos']);
        $this->assertSame(7200, $c['importe_cancelado_centimos']);
        $this->assertSame([], $this->filas('preparacion'));
        $this->assertSame(['No genera demanda adicional'], array_values(array_unique(array_column($this->filas('composiciones'), 'demanda_definitiva'))));
    }

    public function test_exportacion_completa_coincide_con_filas_y_no_con_la_pagina_visible(): void
    {
        $e = $this->escenario();
        for ($i = 0; $i < 31; $i++) {
            $this->pedidoEnvasado($e);
        }

        $pantalla = $this->get(route('indicadores.index', ['economico' => 1]));
        $csv = $this->get(route('indicadores.exportar', ['economico' => 1]));

        $pantalla->assertOk();
        $csv->assertOk()->assertDownload();
        $this->assertCount(25, $pantalla->viewData('resultado')['filas']);
        $this->assertSame(31, $pantalla->viewData('resultado')['cantidad']);
        $contenido = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertCount(32, array_filter(explode("\r\n", $contenido)));
        $this->assertSame(31000, $pantalla->viewData('resultado')['totales']['importe_centimos']);
        $this->assertStringNotContainsString($e['usuario']->email, $contenido);
    }

    public function test_exportar_y_ver_importes_exigen_permisos_independientes(): void
    {
        $e = $this->escenario();
        $this->pedidoAgrupado($e);
        $e['usuario']->role->permisos()->detach(Permiso::whereIn('nombre', ['indicadores.economicos.ver', 'indicadores.exportar'])->pluck('id'));
        $e['usuario']->unsetRelation('role');

        $this->get(route('indicadores.index'))->assertOk()->assertDontSee('importe_centimos');
        $this->get(route('indicadores.index', ['economico' => 1]))->assertForbidden();
        $this->get(route('indicadores.exportar'))->assertForbidden();
        $e['usuario']->role->permisos()->attach(Permiso::where('nombre', 'indicadores.exportar')->value('id'));
        $e['usuario']->unsetRelation('role');
        $this->get(route('indicadores.exportar'))->assertOk();
        $this->get(route('indicadores.exportar', ['economico' => 1]))->assertForbidden();
    }

    public function test_invitado_y_usuario_sin_permiso_no_acceden(): void
    {
        $this->get(route('indicadores.index'))->assertRedirect(route('login'));
        $e = $this->escenario();
        $e['usuario']->role->permisos()->detach(Permiso::where('nombre', 'indicadores.ver')->value('id'));
        $e['usuario']->unsetRelation('role');
        $this->get(route('indicadores.index'))->assertForbidden();
        $this->get(route('indicadores.exportar'))->assertForbidden();
    }

    public function test_seeder_idempotente_respeta_produccion_y_otros_permisos(): void
    {
        $roles = [];
        foreach (['Administrador', 'Operaciones Comerciales', 'Producción/Reparto'] as $n) {
            $roles[$n] = Role::create(['nombre' => $n]);
        }
        $otro = Permiso::create(['nombre' => 'conservar', 'descripcion' => 'Permiso existente']);
        $roles['Administrador']->permisos()->attach($otro);

        $cantidadPrevia = Permiso::count();
        $asignacionesPrevias = DB::table('rol_permiso')->count();
        $this->seed(IndicadoresPermisoSeeder::class);
        $antes = DB::table('rol_permiso')->get()->toJson();
        $this->seed(IndicadoresPermisoSeeder::class);

        $this->assertSame($antes, DB::table('rol_permiso')->get()->toJson());
        $this->assertDatabaseCount('permisos', $cantidadPrevia + 3);
        $this->assertDatabaseCount('rol_permiso', $asignacionesPrevias + 6);
        $this->assertSame(0, $roles['Producción/Reparto']->permisos()->count());
    }

    public function test_filtros_rechazan_columnas_y_periodos_manipulados(): void
    {
        $this->escenario();
        foreach ([['reporte' => 'users'], ['desde' => '2026-10-24', 'hasta' => '2026-10-01'], ['sku' => 'ABC'], ['tamano' => 1], ['tipo' => 'DROP TABLE']] as $f) {
            $this->getJson(route('indicadores.index', $f))->assertUnprocessable();
        }
    }

    public function test_todos_los_reportes_vacios_cargan_con_sus_columnas(): void
    {
        $this->escenario();
        foreach (array_keys(FilasIndicadores::REPORTES) as $reporte) {
            $this->get(route('indicadores.index', ['reporte' => $reporte]))->assertOk();
        }
    }

    public function test_csv_neutraliza_formulas_y_conserva_numeros_legitimos(): void
    {
        $this->escenario();
        $exportador = app(ExportarIndicadoresCsv::class);
        foreach (['=1+1', '+SUM(1)', '-cmd', '@SUM(1)', "\t=1", "\r=1", "\n=1", '  ＝1+1', '＋1', '－1', '＠A1'] as $texto) {
            $this->assertSame("'".$texto, $exportador->celda($texto, 'descripcion'));
        }
        $this->assertSame('-2', $exportador->celda(-2, 'aceptadas'));
        $this->assertSame('0012', $exportador->celda('0012', 'sku'));
        $p = $this->pedidoEnvasado($this->escenarioEnvasado());
        $p->detalles()->update(['descripcion_producto' => '=HYPERLINK("https://example.test";"x")']);
        $csv = $this->get(route('indicadores.exportar'))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_historial_incluye_lineas_retiradas_sin_sumarlas_en_comercial(): void
    {
        $e = $this->escenario();
        $p = $this->pedidoAgrupado($e);
        $entrada = $this->entradaUnidades($e, [19, 0, 6]);
        $entrada['cliente_id'] = $p->cliente_id;
        foreach ($p->detalles()->get() as $i => $d) {
            $entrada['detalles'][$i]['id'] = $d->id;
        }
        $entrada['_version'] = $p->version;
        $entrada['motivo'] = 'Retiro auditado de una línea manteniendo las agrupaciones anteriores.';
        app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario'], $p);
        $this->assertSame([], $this->filas('composiciones'));
        $historial = $this->filas('composiciones', ['historial' => 1]);
        $this->assertCount(6, $historial);
        $this->assertSame(['No genera demanda adicional'], array_values(array_unique(array_column($historial, 'demanda_definitiva'))));
        $this->assertCount(2, $this->filas('comercial'));
        $this->assertSame(25, array_sum(array_column($this->filas('fisicas'), 'unidades_fisicas')));
    }

    public function test_filtros_vigentes_coinciden_en_resumen_y_csv_y_rechazan_composicion_retirada(): void
    {
        $e = $this->escenario();
        $p = $this->pedidoAgrupado($e);
        $this->pedidoAgrupado($e);
        $this->getJson(route('indicadores.index', ['composicion' => 'pendiente']))->assertUnprocessable()->assertJsonValidationErrors('composicion');
        $this->getJson(route('indicadores.exportar', ['composicion' => 'pendiente']))->assertUnprocessable()->assertJsonValidationErrors('composicion');
        $r = $this->get(route('indicadores.index', ['pedido_id' => $p->id, 'economico' => 1]))->assertOk();
        $csv = $this->get(route('indicadores.exportar', ['pedido_id' => $p->id, 'economico' => 1, 'resumen' => 1]));
        $this->assertSame(1, (int) $r->viewData('resultado')['comercial']['pedidos_registrados']);
        $this->assertSame(7200, $r->viewData('resultado')['totales']['importe_centimos']);
        $this->assertStringContainsString('importe_centimos;7200;0;', $csv->streamedContent());
    }

    public function test_html_escapa_descripcion_y_csv_no_crea_columnas_con_separadores_inyectados(): void
    {
        $e = $this->escenario();
        $p = $this->pedidoEnvasado($e);
        $texto = '<script>alert(1)</script>;";=1+1';
        $p->detalles()->update(['descripcion_producto' => $texto]);

        $html = $this->get(route('indicadores.index'));
        $csv = $this->get(route('indicadores.exportar'))->streamedContent();

        $html->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $fp = fopen('php://temp', 'w+b');
        fwrite($fp, $csv);
        rewind($fp);
        $cabecera = fgetcsv($fp, null, ';', '"', '');
        $fila = fgetcsv($fp, null, ';', '"', '');
        fclose($fp);
        $this->assertCount(count($cabecera), $fila);
        $this->assertContains($texto, $fila);
    }

    public function test_filtros_ajenos_no_se_ignoran_y_la_preparacion_incluye_atrasados_por_defecto(): void
    {
        $e = $this->escenario();
        $p = $this->pedidoEnvasado($e);
        $p->update(['fecha_entrega' => '2026-09-15']);

        $this->getJson(route('indicadores.index', ['reporte' => 'inventario', 'pedido_id' => $p->id]))->assertUnprocessable();
        $this->get(route('indicadores.index', ['reporte' => 'preparacion']))->assertOk()->assertViewHas('resultado', fn ($r): bool => $r['cantidad'] === 1);
    }
}
