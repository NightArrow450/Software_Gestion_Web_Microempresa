<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Services\LecturaPedido;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisponibilidadInventarioController extends Controller
{
    public function index(Request $request): View
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos,id'],
            'historico_materiales' => ['sometimes', 'boolean'],
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

        $estadosPendientes = ['confirmado', 'en_preparacion', 'listo_despacho', 'despacho_parcial'];
        $pedidosDisponibles = Pedido::whereIn('estado', $estadosPendientes)
            ->orderByDesc('id')->paginate(20, ['id', 'numero', 'cliente_nombre', 'estado'], 'pedidos_pagina')->withQueryString();

        $pedidoConsulta = null;
        $revision = collect();
        $puedeAtender = null;

        if (! empty($datos['pedido_id'])) {
            $pedidoConsulta = Pedido::with(['detalles.presentacion'])
                ->whereIn('estado', $estadosPendientes)
                ->findOrFail($datos['pedido_id']);

            $solicitado = [];
            $lecturaPedido = app(LecturaPedido::class);
            foreach ($lecturaPedido->pendientes($pedidoConsulta) as $pendiente) {
                $solicitado['pendiente-'.$pendiente['detalle_id']] = ['sku' => '—', 'nombre' => $pendiente['descripcion'], 'necesario' => $pendiente['unidades'], 'presentacion_id' => null, 'error' => 'Línea anterior sin SKU verificable: requiere revisión comercial; no se declara cobertura completa.'];
            }
            foreach ($lecturaPedido->lineasFisicas($pedidoConsulta) as $detalle) {
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
                        'necesario' => 0, 'en_espera' => 0,
                        'presentacion_id' => $id,
                        'error' => $multiplicador < 1 ? 'Empaque sin cantidad de unidades válida.' : null,
                    ];
                }
                $solicitado[$id]['necesario'] += $unidades;
                $solicitado[$id]['en_espera'] += $pedidoConsulta->modelo_comercial === 'unidades' ? min($unidades, (int) $detalle->cantidad_espera) : 0;
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
                $fila['en_espera'] ??= 0;
                $fila['activo'] = $fila['necesario'] === null ? null : max(0, $fila['necesario'] - $fila['en_espera']);
                $fila['faltante_activo'] = $fila['activo'] === null ? null : max(0, $fila['activo'] - $fila['disponible']);
                $fila['faltante_total'] = $fila['necesario'] === null ? null : max(0, $fila['necesario'] - $fila['disponible']);
                $fila['alcanza'] = ! $fila['error'] && $fila['disponible'] >= $fila['necesario'] && $fila['en_espera'] === 0;

                return $fila;
            })->values();
            $puedeAtender = $revision->isNotEmpty() && $revision->every(fn ($f) => $f['alcanza']);
        }

        // Insumos y materiales de envase: inventario distinto a los productos terminados.
        $insumos = $request->boolean('historico_materiales') ? Insumo::query()
            ->where('estado', true)
            ->when($buscar !== '', fn ($q) => $q->where(function ($f) use ($buscar) {
                $f->where('codigo', 'like', "%{$buscar}%")
                    ->orWhere('nombre', 'like', "%{$buscar}%")
                    ->orWhere('categoria', 'like', "%{$buscar}%");
            }))
            ->orderBy('categoria')->orderBy('nombre')
            ->paginate(15, ['*'], 'insumos_pagina')->withQueryString() : null;

        return view('inventory.index', compact(
            'presentaciones', 'cantidades', 'pedidosDisponibles',
            'pedidoConsulta', 'revision', 'puedeAtender', 'buscar', 'insumos'
        ));
    }
}
