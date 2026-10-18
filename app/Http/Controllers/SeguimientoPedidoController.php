<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\Request;
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

        return view('seguimiento.show', compact('pedido'));
    }

    public function trazabilidad(Pedido $pedido): View
    {
        // La relación impide mostrar eventos de otros pedidos.
        $pedido->load([
            'historialEstados' => fn ($q) => $q->with('usuario')
                ->orderBy('created_at')->orderBy('id'),
        ]);

        return view('seguimiento.trazabilidad', compact('pedido'));
    }

    public function historial(Request $request): View
    {
        $data = $request->validate([
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'cliente' => ['nullable', 'string', 'max:180'],
            'estado' => ['nullable', 'in:registrado,observado,confirmado,cancelado,en_preparacion,listo_despacho,despachado,entregado'],
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

        $estados = [
            'registrado' => 'Registrado', 'observado' => 'Observado',
            'confirmado' => 'Confirmado', 'cancelado' => 'Cancelado',
            'en_preparacion' => 'En preparación', 'listo_despacho' => 'Listo para despacho',
            'despachado' => 'Despachado', 'entregado' => 'Entregado',
        ];

        return view('seguimiento.historial', compact('pedidos', 'filtros', 'estados'));
    }
}
