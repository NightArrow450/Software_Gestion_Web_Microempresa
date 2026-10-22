<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisponibilidadInventarioController extends Controller
{
    public function index(Request $request): View
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos,id'],
        ]);
        $buscar = trim($datos['buscar'] ?? '');

        $presentaciones = PresentacionProducto::with(['producto', 'variante'])
            ->where('estado', true)
            ->whereHas('producto', fn ($q) => $q->where('estado', true))
            ->where(function ($q) {
                $q->whereNull('variante_producto_id')
                    ->orWhereHas('variante', fn ($v) => $v->where('estado', true));
            })
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($q) use ($buscar) {
                    $q->where('codigo_sku', 'like', "%{$buscar}%")
                        ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->orderBy('codigo_sku')
            ->paginate(15)
            ->withQueryString();

        $cantidades = Inventario::whereIn('presentacion_producto_id', $presentaciones->pluck('id'))
            ->pluck('cantidad_actual', 'presentacion_producto_id');

        $pedidosDisponibles = Pedido::where('estado', '!=', 'cancelado')
            ->orderByDesc('id')->limit(100)->get(['id', 'cliente_nombre', 'estado']);

        $pedidoConsulta = null;
        $revision = collect();
        $puedeAtender = null;

        if (! empty($datos['pedido_id'])) {
            $pedidoConsulta = Pedido::with(['detalles.presentacion'])
                ->where('estado', '!=', 'cancelado')
                ->findOrFail($datos['pedido_id']);

            $solicitado = [];
            foreach ($pedidoConsulta->detalles as $detalle) {
                $id = $detalle->presentacion_producto_id;
                $presentacion = $detalle->presentacion;
                if (! $id || ! $presentacion) {
                    $clave = 'sin-presentacion-'.$detalle->id;
                    $solicitado[$clave] = [
                        'sku' => $detalle->codigo_sku,
                        'nombre' => $detalle->descripcion_producto,
                        'necesario' => (int) $detalle->cantidad,
                        'presentacion_id' => null,
                        'error' => 'La presentación del pedido ya no existe.',
                    ];

                    continue;
                }
                $multiplicador = $detalle->modalidad === 'empaque'
                    ? (int) $detalle->unidadesPorEmpaqueEfectivas() : 1;
                $unidades = $multiplicador * (int) $detalle->cantidad;
                if (! isset($solicitado[$id])) {
                    $solicitado[$id] = [
                        'sku' => $detalle->codigo_sku,
                        'nombre' => $detalle->descripcion_producto,
                        'necesario' => 0,
                        'presentacion_id' => $id,
                        'error' => $multiplicador < 1 ? 'Empaque sin cantidad de unidades válida.' : null,
                    ];
                }
                $solicitado[$id]['necesario'] += $unidades;
                if ($multiplicador < 1) {
                    $solicitado[$id]['error'] = 'Empaque sin cantidad de unidades válida.';
                }
            }
            $cantidadesPedido = Inventario::whereIn('presentacion_producto_id',
                array_filter(array_column($solicitado, 'presentacion_id')))
                ->pluck('cantidad_actual', 'presentacion_producto_id');

            $revision = collect($solicitado)->map(function ($fila) use ($cantidadesPedido) {
                $fila['disponible'] = $fila['presentacion_id']
                    ? (int) ($cantidadesPedido[$fila['presentacion_id']] ?? 0) : 0;
                $fila['alcanza'] = ! $fila['error'] && $fila['disponible'] >= $fila['necesario'];

                return $fila;
            })->values();
            $puedeAtender = $revision->isNotEmpty() && $revision->every(fn ($f) => $f['alcanza']);
        }

        // Insumos y materiales de envase: inventario distinto a los productos terminados.
        $insumos = Insumo::query()
            ->where('estado', true)
            ->when($buscar !== '', fn ($q) => $q->where(function ($f) use ($buscar) {
                $f->where('codigo', 'like', "%{$buscar}%")
                    ->orWhere('nombre', 'like', "%{$buscar}%")
                    ->orWhere('categoria', 'like', "%{$buscar}%");
            }))
            ->orderBy('categoria')->orderBy('nombre')
            ->paginate(15, ['*'], 'insumos_pagina')->withQueryString();

        return view('inventory.index', compact(
            'presentaciones', 'cantidades', 'pedidosDisponibles',
            'pedidoConsulta', 'revision', 'puedeAtender', 'buscar', 'insumos'
        ));
    }
}
