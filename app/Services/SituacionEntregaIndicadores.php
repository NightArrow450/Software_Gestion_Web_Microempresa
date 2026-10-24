<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SituacionEntregaIndicadores
{
    public function filas(array $f): \Generator
    {
        $pedidos = DB::table('pedidos as pe')->whereIn('pe.estado', ControlEntregaPedido::ESTADOS);
        FiltrosIndicadores::pedido($pedidos, $f);
        FiltrosIndicadores::periodo($pedidos, 'pe.fecha_pedido', $f);
        foreach ($pedidos->select('pe.id', 'pe.estado')->lazyById(100, 'pe.id', 'id')->chunk(100) as $lote) {
            $ids = $lote->pluck('id');
            $incidencias = DB::table('incidencias_entrega')->whereIn('pedido_id', $ids)->get()->keyBy('pedido_id');
            $fotos = DB::table('incidencia_entrega_eventos')->whereIn('incidencia_id', $incidencias->pluck('id'))->where('tipo', 'despacho_base')->get()->groupBy('incidencia_id');
            $resultados = DB::table('entrega_pedido_detalles as ed')->join('entregas_pedido as e', 'e.id', '=', 'ed.entrega_id')->whereIn('e.incidencia_id', $incidencias->pluck('id'))->select('ed.*', 'e.incidencia_id')->get()->groupBy('incidencia_id');
            $retornos = DB::table('retorno_pedido_detalles as rd')->join('retornos_pedido as r', 'r.id', '=', 'rd.retorno_id')->whereIn('r.incidencia_id', $incidencias->pluck('id'))->select('rd.*', 'r.incidencia_id')->get()->groupBy('incidencia_id');
            $detalles = DB::table('pedido_detalles')->whereIn('pedido_id', $ids)->whereNull('retirado_en')->get()->groupBy('pedido_id');
            $reincorporaciones = [];
            foreach (app(ActividadIndicadores::class)->aprobaciones(['_pedidos_ids' => $ids->all(), '_acumulado_actual' => true]) as $aprobacion) {
                if ($aprobacion['reincorporadas'] !== null) {
                    $id = $aprobacion['retorno_detalle_id'];
                    $reincorporaciones[$id] = ($reincorporaciones[$id] ?? 0) + $aprobacion['reincorporadas'];
                }
            }
            foreach ($lote as $pedido) {
                $inc = $incidencias->get($pedido->id);
                $eventos = $inc ? $fotos->get($inc->id, collect()) : collect();
                $lineas = $eventos->count() === 1 ? json_decode($eventos->sole()->datos, true, 512, JSON_THROW_ON_ERROR)['lineas'] : [];
                if ($lineas === []) {
                    foreach ($detalles->get($pedido->id, collect()) as $d) {
                        $lineas[$d->id] = ['id' => $d->id, 'presentacion_id' => $d->presentacion_producto_id, 'sku' => $d->codigo_sku, 'despachadas' => null, 'importe_centimos' => null, 'verificada' => false];
                    }
                }
                $entregas = $inc ? $resultados->get($inc->id, collect()) : collect();
                $recepciones = $inc ? $retornos->get($inc->id, collect()) : collect();
                foreach ($lineas as $linea) {
                    if ((! empty($f['sku']) && $linea['sku'] !== $f['sku']) || (! empty($f['presentacion_id']) && (int) $linea['presentacion_id'] !== (int) $f['presentacion_id'])) {
                        continue;
                    }
                    $clave = ControlEntregaPedido::claveFisica((int) $linea['id'], $linea['componente_id'] ?? null);
                    $filtrar = fn ($r): bool => ControlEntregaPedido::claveFisica((int) $r->pedido_detalle_id, $r->despacho_componente_id === null ? null : (int) $r->despacho_componente_id) === $clave;
                    $e = $entregas->filter($filtrar);
                    $r = $recepciones->filter($filtrar);
                    $verificable = ($linea['verificada'] ?? false) && ! ($pedido->estado === 'entregado' && $entregas->isEmpty());
                    $a = $verificable ? (int) $e->sum('aceptadas') : null;
                    $rechazadas = $verificable ? (int) $e->sum('rechazadas') : null;
                    $n = $verificable ? $linea['despachadas'] : null;
                    $recibidas = (int) $r->sum(fn ($x): int => $x->aptas + $x->danadas + $x->en_revision);
                    $aptas = (int) $r->sum(fn ($x): int => $x->aptas + $x->revisadas_aptas);
                    $danadas = (int) $r->sum(fn ($x): int => $x->danadas + $x->revisadas_danadas);
                    $revision = (int) $r->sum(fn ($x): int => $x->en_revision - $x->revisadas_aptas - $x->revisadas_danadas);
                    $noRecuperadas = (int) $r->sum('no_recuperadas');
                    $verificadas = (int) $r->sum(fn ($retorno): int => $reincorporaciones[$retorno->id] ?? 0);
                    $reincorporadas = (int) $r->sum('reincorporadas') === $verificadas ? $verificadas : null;
                    $valor = $verificable ? app(ActividadIndicadores::class)->valorar($linea, $a, $rechazadas) : null;
                    yield ['pedido_id' => $pedido->id, 'estado_actual' => $pedido->estado, 'linea_id' => $linea['id'], 'despacho_componente_id' => $linea['componente_id'] ?? null, 'presentacion_id' => $linea['presentacion_id'], 'sku' => $linea['sku'],
                        'despachadas' => $n, 'aceptadas' => $a, 'rechazadas' => $rechazadas, 'pendientes' => $n === null ? null : $n - $a - $rechazadas, 'recibidas' => $recibidas,
                        'aptas' => $aptas, 'danadas' => $danadas, 'por_revisar' => $revision, 'no_recuperadas' => $noRecuperadas, 'reincorporadas' => $reincorporadas,
                        'por_retornar' => $rechazadas === null ? null : $rechazadas - $recibidas - $noRecuperadas, 'por_aprobar' => $reincorporadas === null ? null : $aptas - $reincorporadas,
                        'aceptado_centimos' => $valor[0] ?? null, 'rechazado_centimos' => $valor[1] ?? null, 'pendiente_centimos' => $valor === null ? null : $linea['importe_centimos'] - $valor[0] - $valor[1],
                        'verificable' => ! $verificable ? 'No: histórico sin desglose verificable' : ($reincorporadas === null ? 'No: reincorporación sin movimiento concordante' : 'Sí')];
                }
            }
        }
    }
}
