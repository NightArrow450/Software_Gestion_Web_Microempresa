<?php

namespace Tests\MySql;

use App\Models\Permiso;
use App\Services\CorregirEnvasado;
use App\Services\FilasIndicadores;
use App\Services\FiltrosIndicadores;
use App\Services\GestionarSurtidos;
use App\Services\RegistrarEnvasado;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\SurtidosEscenario;

class QuinceExportacionesMySqlTest extends PruebaEntregaMySql
{
    use SurtidosEscenario;

    public function test_quince_csv_con_datos_ficticios_operativos_y_filtros(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00', 'America/Lima'));
        $e = $this->escenarioSurtido();
        foreach (['indicadores.ver', 'indicadores.economicos.ver', 'indicadores.exportar', 'cumplimiento.incidencias.revisar'] as $n) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $n], ['descripcion' => $n])->id);
        }$e['usuario']->unsetRelation('role');
        $normal = $this->pedidoEnvasado($e);
        $sur = $this->pedidoEnvasado($e);
        $d = $sur->detalles->sole();
        $d->update(['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'], 'modalidad' => 'empaque', 'cantidad' => 2, 'unidades_por_empaque' => 12, 'precio_unitario' => 24, 'importe' => 48]);
        app(GestionarSurtidos::class)->guardar($d, $this->paquetesSurtidos($e), $e['usuario'], 'Composición ficticia para revisión de los quince CSV.');
        $sur->update(['total' => 48]);
        $pend = $this->pedidoEnvasado($e);
        $dp = $pend->detalles->sole();
        $dp->update(['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'], 'modalidad' => 'empaque', 'cantidad' => 1, 'unidades_por_empaque' => 12, 'precio_unitario' => 24, 'importe' => 24]);
        app(GestionarSurtidos::class)->guardar($dp, [['estado' => 'pendiente', 'componentes' => [['presentacion_producto_id' => $e['skus'][0]->id, 'cantidad' => 4]]]], $e['usuario'], 'Composición pendiente ficticia para revisión de CSV.');
        $pend->update(['total' => 24]);
        $this->pedidoEnvasado($e, 'cancelado');
        $env = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 10], $e['usuario']);
        app(CorregirEnvasado::class)->ejecutar($env, ['clasificacion' => 'error_administrativo', 'cantidad' => 2, 'revertidas_previas' => 0, 'motivo' => 'Dos unidades ficticias registradas por error.', 'devoluciones' => [$env->consumos->sole()->id => 2]], $e['usuario']);
        $desp = $this->pedidoEnvasado($e, 'listo_despacho');
        $dd = $desp->detalles->sole();
        $this->post(route('cumplimiento.despachar', $desp))->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.resultado-entrega', $desp), ['version' => 0, 'detalles' => [['pedido_detalle_id' => $dd->id, 'aceptadas' => 3, 'rechazadas' => 2, 'motivo' => 'Rechazo de dos unidades ficticias para revisión.']]])->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.retornos', $desp), ['version' => 1, 'observaciones' => 'Recepción de dos unidades ficticias para revisión.', 'detalles' => [['pedido_detalle_id' => $dd->id, 'aptas' => 1, 'danadas' => 0, 'en_revision' => 1, 'no_recuperadas' => 0]]])->assertSessionHasNoErrors();
        $rd = DB::table('retorno_pedido_detalles')->value('id');
        $this->post(route('cumplimiento.incidencia.clasificar', $desp), ['version' => 2, 'motivo' => 'Clasificación ficticia de una unidad dañada.', 'retorno_detalle_id' => $rd, 'aptas' => 0, 'danadas' => 1])->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.aprobar-retorno', $desp), ['version' => 3, 'retorno_detalle_id' => $rd, 'cantidad' => 1, 'motivo' => 'Unidad ficticia apta aprobada para revisión.'])->assertSessionHasNoErrors();
        $antes = DB::table('movimientos_inventario')->count();
        foreach (array_keys(FilasIndicadores::REPORTES) as $reporte) {
            $f = FiltrosIndicadores::validar(['reporte' => $reporte, 'economico' => 1]);
            $csv = $this->get(route('indicadores.exportar', $f))->assertOk()->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            $this->assertTrue(mb_check_encoding($csv, 'UTF-8'));
            $h = fopen('php://temp', 'w+b');
            fwrite($h, substr($csv, 3));
            rewind($h);
            $cab = fgetcsv($h, null, ';', '"', '');
            $this->assertSame([...app(FilasIndicadores::class)->campos($f), 'periodo_desde', 'periodo_hasta', 'generado_en_lima', 'alcance'], $cab);
            $n = 0;
            while (($row = fgetcsv($h, null, ';', '"', '')) !== false) {
                $this->assertCount(count($cab), $row);
                $n++;
            }fclose($h);
            $this->assertGreaterThan(0, $n, $reporte);
            $this->assertSame(count(iterator_to_array(app(FilasIndicadores::class)->filas($f))), $n);
            $campo = in_array('pedido_id', FiltrosIndicadores::APLICABLES[$reporte], true) ? 'pedido_id' : 'presentacion_id';
            $valor = $campo === 'pedido_id' ? $desp->id : $e['presentacion']->id;
            $ff = FiltrosIndicadores::validar(['reporte' => $reporte, 'economico' => 1, $campo => $valor]);
            $this->get(route('indicadores.index', $ff))->assertOk();
            $this->get(route('indicadores.exportar', $ff))->assertOk();
            fwrite(STDERR, 'CSV_REVISADO '.$reporte.' filas='.$n.PHP_EOL);
        }
        $this->assertSame($antes, DB::table('movimientos_inventario')->count());
        $this->assertSame(104, (int) DB::table('inventarios')->where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
    }
}
