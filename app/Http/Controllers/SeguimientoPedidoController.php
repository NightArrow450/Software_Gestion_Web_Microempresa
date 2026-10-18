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
}
