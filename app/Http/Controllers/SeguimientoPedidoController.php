<?php

namespace App\Http\Controllers;

use App\EstadoPedido;
use App\Models\Pedido;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarPedidoUnidades;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Módulo Sprint 5: consultas autenticadas; nunca modifica pedidos o estados.
 */
class SeguimientoPedidoController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'codigo' => ['nullable', 'string', 'regex:/^\d{1,12}$/'],
            'cliente' => ['nullable', 'string', 'max:180'],
        ]);

        $codigo = trim((string) ($data['codigo'] ?? ''));
        $cliente = trim((string) ($data['cliente'] ?? ''));

        $pedidos = Pedido::query()
            ->when($codigo !== '', fn ($q) => $q->whereKey((int) $codigo))
            ->when($cliente !== '', fn ($q) => $q->where('cliente_nombre', 'like', '%'.$cliente.'%'))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('seguimiento.index', compact('pedidos', 'codigo', 'cliente'));
    }

    public function show(Pedido $pedido): View
    {
        // Datos en pedidos del Sprint 2 y fechas logísticas del Sprint 4.
        $pedido->load(['cliente', 'vendedor']);

        $controlEntrega = app(ControlEntregaPedido::class);
        $resumenEntrega = $controlEntrega->disponible() ? $controlEntrega->resumen($pedido) : null;

        $versionesPedido = app(GestionarPedidoUnidades::class)->historial($pedido);
        $cambiosOriginales = app(GestionarPedidoUnidades::class)->cambiosOriginales($pedido);

        return view('seguimiento.show', compact('pedido', 'resumenEntrega', 'versionesPedido', 'cambiosOriginales'));
    }

    public function trazabilidad(Pedido $pedido): View
    {
        // La relación impide mostrar eventos de otros pedidos.
        $pedido->load([
            'historialEstados' => fn ($q) => $q->with('usuario')
                ->orderBy('created_at')->orderBy('id'),
        ]);

        $controlEntrega = app(ControlEntregaPedido::class);
        $resumenEntrega = $controlEntrega->disponible() ? $controlEntrega->resumen($pedido) : null;

        $versionesPedido = app(GestionarPedidoUnidades::class)->historial($pedido);
        $cambiosOriginales = app(GestionarPedidoUnidades::class)->cambiosOriginales($pedido);

        return view('seguimiento.trazabilidad', compact('pedido', 'resumenEntrega', 'versionesPedido', 'cambiosOriginales'));
    }

    public function historial(Request $request): View
    {
        $data = $request->validate([
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'cliente' => ['nullable', 'string', 'max:180'],
            'estado' => ['nullable', Rule::in(array_keys(EstadoPedido::etiquetas()))],
        ]);

        $filtros = [
            'fecha_desde' => $data['fecha_desde'] ?? '',
            'fecha_hasta' => $data['fecha_hasta'] ?? '',
            'cliente' => trim((string) ($data['cliente'] ?? '')),
            'estado' => $data['estado'] ?? '',
        ];

        $pedidos = Pedido::query()
            ->when($filtros['fecha_desde'] !== '', fn ($q) => $q->whereDate('fecha_pedido', '>=', $filtros['fecha_desde']))
            ->when($filtros['fecha_hasta'] !== '', fn ($q) => $q->whereDate('fecha_pedido', '<=', $filtros['fecha_hasta']))
            ->when($filtros['cliente'] !== '', fn ($q) => $q->where('cliente_nombre', 'like', '%'.$filtros['cliente'].'%'))
            ->when($filtros['estado'] !== '', fn ($q) => $q->where('estado', $filtros['estado']))
            ->orderByDesc('fecha_pedido')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        $estados = EstadoPedido::etiquetas();

        return view('seguimiento.historial', compact('pedidos', 'filtros', 'estados'));
    }
}
