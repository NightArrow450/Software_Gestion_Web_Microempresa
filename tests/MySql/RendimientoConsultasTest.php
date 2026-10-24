<?php

namespace Tests\MySql;

use App\Models\Inventario;
use App\Models\Permiso;
use App\Models\User;
use App\Services\RegistrarEnvasado;
use App\Services\RegistrarResultadoEntrega;
use App\Services\RegistrarRetornoPedido;
use App\Services\TransicionesPedido;
use App\Services\VerificadorInventario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\EnvasadoEscenario;

class RendimientoConsultasTest extends PruebaEntregaMySql
{
    use EnvasadoEscenario;

    private bool $midiendo = false;

    private int $consultas = 0;

    private float $tiempoSql = 0;

    public static function tamanos(): array
    {
        return ['pequeño' => [3], 'grande' => [300]];
    }

    #[DataProvider('tamanos')]
    public function test_lecturas_y_operaciones_conservan_resultados_en_mysql_independiente(int $cantidad): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'America/Lima'));
        $e = $this->escenarioEnvasado(1000000, 1000000);
        $e['presentacion']->update(['codigo_sku' => 'RENDIMIENTO-1L']);
        foreach (['cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar', 'cumplimiento.incidencias.revisar', 'cumplimiento.incidencias.corregir', 'seguimiento.ver'] as $nombre) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }
        $this->actingAs($e['usuario']);
        $demanda = 0;
        $primero = null;
        for ($i = 0; $i < $cantidad; $i++) {
            $pedido = $this->pedidoEnvasado($e);
            $primero ??= $pedido;
            $factor = [1, 12, 15][$i % 3];
            $unidades = $factor === 1 ? 5 : 2;
            $pedido->detalles()->firstOrFail()->update(['modalidad' => $factor === 1 ? 'unidad' : 'empaque', 'cantidad' => $unidades, 'unidades_por_empaque' => $factor === 1 ? null : $factor, 'precio_unitario' => 2 * $factor, 'importe' => $unidades * 2 * $factor]);
            $pedido->update(['total' => $unidades * 2 * $factor]);
            $demanda += $unidades * $factor;
            foreach ([null, $pedido->id] as $pedidoId) {
                app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 3, 'pedido_id' => $pedidoId], $e['usuario']);
            }
        }
        $terminal = $this->pedidoEnvasado($e, 'listo_despacho');
        $detalle = $terminal->detalles()->firstOrFail();
        $detalle->update(['cantidad' => $cantidad + 2, 'importe' => ($cantidad + 2) * 2]);
        $terminal->update(['total' => ($cantidad + 2) * 2]);
        app(TransicionesPedido::class)->registrar($terminal, 'listo_despacho', 'despachado', $e['usuario']->id, [], 'Despacho de prueba de rendimiento.');
        app(RegistrarResultadoEntrega::class)->ejecutar($terminal->fresh(), ['version' => 0, 'detalles' => [['pedido_detalle_id' => $detalle->id, 'aceptadas' => 2, 'rechazadas' => $cantidad, 'motivo' => 'Rechazo documentado exclusivamente de prueba.']]], $e['usuario']);
        for ($i = 0; $i < $cantidad; $i++) {
            $version = DB::table('incidencias_entrega')->where('pedido_id', $terminal->id)->value('version');
            app(RegistrarRetornoPedido::class)->ejecutar($terminal->fresh(), ['version' => $version, 'observaciones' => 'Recepción parcial de prueba de rendimiento.', 'detalles' => [['pedido_detalle_id' => $detalle->id, 'aptas' => $i === $cantidad - 1 ? 0 : 1, 'danadas' => $i === $cantidad - 1 ? 1 : 0, 'en_revision' => 0, 'no_recuperadas' => 0]]], $e['usuario']);
        }
        $recepcion = DB::table('retorno_pedido_detalles')->where('pedido_detalle_id', $detalle->id)->orderBy('id')->first();
        $despacho = $this->pedidoEnvasado($e, 'listo_despacho');
        DB::listen(function (QueryExecuted $consulta): void {
            if ($this->midiendo) {
                $this->consultas++;
                $this->tiempoSql += $consulta->time;
            }
        });
        $operaciones = [
            'preparacion' => function () use ($demanda, $cantidad): array {
                $r = $this->get(route('cumplimiento.lista-preparacion'))->assertOk();
                $filas = $r->viewData('filas');
                $this->assertSame($demanda, $filas->sum('total_unidades'));
                $this->assertSame($cantidad * 6, $filas->sum('envasadas'));
                $this->assertSame($cantidad * 3, $filas->sum('vinculadas'));

                return $filas->map(fn ($f) => array_intersect_key($f, array_flip(['total_unidades', 'envasadas', 'vinculadas', 'stock_disponible', 'faltante', 'desglose'])))->values()->all();
            },
            'pedidos' => fn (): array => ['total' => $this->get(route('pedidos.index'))->assertOk()->viewData('pedidos')->total()],
            'inventario' => function (): array {
                $r = $this->get(route('inventario.index'))->assertOk();

                return ['stock' => array_values($r->viewData('cantidades')->all()), 'total' => $r->viewData('presentaciones')->total()];
            },
            'alertas' => fn (): array => $this->get(route('inventario.stock'))->assertOk()->viewData('resumen'),
            'envasados' => fn (): array => ['total' => $this->get(route('envasado.index'))->assertOk()->viewData('registros')->total()],
            'seguimiento' => fn (): array => ['total' => $this->get(route('seguimiento.historial'))->assertOk()->viewData('pedidos')->total()],
            'entrega_retornos' => function () use ($terminal): array {
                $r = $this->get(route('cumplimiento.show', $terminal))->assertOk()->viewData('resumenEntrega');

                return array_values(array_map(fn ($f) => array_intersect_key($f, array_flip(['despachadas', 'aceptadas', 'rechazadas', 'pendientes', 'retornadas', 'aptas', 'danadas', 'reincorporadas', 'por_retornar', 'por_aprobar', 'importe_centimos', 'importe_aceptado_centimos', 'importe_rechazado_centimos'])), $r['lineas']));
            },
            'verificador' => function (): array {
                $r = app(VerificadorInventario::class)->revisar();
                $this->assertTrue($r['correcto']);
                foreach (['saldo_inicial', 'consistentes', 'sin_historial'] as $grupo) {
                    $r[$grupo] = array_map(fn ($f) => array_diff_key($f, ['id' => true]), $r[$grupo]);
                }

                return $r;
            },
        ];
        foreach ($operaciones as $nombre => $operacion) {
            $this->medir($nombre, $cantidad, $e['usuario'], $operacion);
        }
        $this->medir('registrar_envasado', $cantidad, $e['usuario'], function () use ($e, $primero): array {
            $this->post(route('envasado.store'), ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 2, 'pedido_id' => $primero->id])->assertRedirect();

            return ['stock' => (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'), 'insumo' => $e['insumo']->fresh()->cantidad_actual];
        }, true);
        $this->medir('despachar', $cantidad, $e['usuario'], function () use ($e, $despacho): array {
            $this->post(route('cumplimiento.despachar', $despacho))->assertRedirect();

            return ['stock' => (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'), 'estado' => $despacho->fresh()->estado];
        }, true);
        $this->medir('aprobar_retorno', $cantidad, $e['usuario'], function () use ($terminal, $recepcion, $e): array {
            $this->post(route('cumplimiento.aprobar-retorno', $terminal), ['version' => DB::table('incidencias_entrega')->where('pedido_id', $terminal->id)->value('version'), 'retorno_detalle_id' => $recepcion->id, 'cantidad' => 1, 'motivo' => 'Autorización de prueba de rendimiento.'])->assertRedirect();

            return ['stock' => (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'), 'reincorporadas' => (int) DB::table('retorno_pedido_detalles')->where('id', $recepcion->id)->value('reincorporadas')];
        }, true);
    }

    private function medir(string $nombre, int $tamano, User $usuario, callable $operacion, bool $escritura = false): void
    {
        $muestras = [];
        $huella = null;
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($usuario->fresh());
            if ($escritura) {
                DB::beginTransaction();
            }
            gc_collect_cycles();
            memory_reset_peak_usage();
            $memoria = memory_get_usage();
            $this->consultas = 0;
            $this->tiempoSql = 0;
            $inicio = hrtime(true);
            $this->midiendo = true;
            try {
                $resultado = $operacion();
            } finally {
                $this->midiendo = false;
                $ms = (hrtime(true) - $inicio) / 1000000;
                $pico = max(0, memory_get_peak_usage() - $memoria);
                if ($escritura) {
                    DB::rollBack();
                }
            }
            $actual = hash('sha256', json_encode($resultado, JSON_THROW_ON_ERROR));
            $huella ??= $actual;
            $this->assertSame($huella, $actual, $nombre);
            if ($i > 0) {
                $muestras[] = ['consultas' => $this->consultas, 'ms' => round($ms, 3), 'sql_ms' => round($this->tiempoSql, 3), 'pico_bytes' => $pico];
            }
        }
        $registro = ['etapa' => getenv('RENDIMIENTO_ETAPA') ?: 'regresion', 'operacion' => $nombre, 'tamano' => $tamano, 'resultado_sha256' => $huella, 'muestras' => $muestras];
        if ($ruta = getenv('RENDIMIENTO_SALIDA')) {
            file_put_contents($ruta, json_encode($registro, JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
