<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Services\TransicionCumplimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CumplimientoPedidoController extends Controller
{
    public function index(Request $request): View
    {
        $estados = ['confirmado', 'en_preparacion', 'listo_despacho', 'despachado', 'entregado'];
        $estado = (string) $request->query('estado', '');
        if (!in_array($estado, $estados, true)) {
            $estado = '';
        }

        $pedidos = Pedido::query()->with('cliente')
            ->whereIn('estado', $estados)
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->orderBy('fecha_entrega')
            ->orderBy('id')
            ->paginate(15)->withQueryString();

        return view('cumplimiento.index', compact('pedidos', 'estado', 'estados'));
    }

    public function show(Pedido $pedido): View
    {
        $pedido->load([
            'detalles', 'cliente', 'vendedor',
            'historialEstados' => fn ($q) => $q->with('usuario')->orderBy('id'),
        ]);

        return view('cumplimiento.show', compact('pedido'));
    }

    public function iniciarPreparacion(
        Request $request,
        Pedido $pedido,
        TransicionCumplimiento $transiciones
    ): RedirectResponse {
        $transiciones->registrar(
            $pedido, 'confirmado', 'en_preparacion', $request->user()->id,
            ['preparacion_iniciada_por_id' => $request->user()->id,
             'preparacion_iniciada_en' => now()],
            'Preparación iniciada.'
        );

        return redirect()->route('cumplimiento.show', $pedido)
            ->with('success', 'Se inició la preparación del pedido.');
    }

    public function finalizarPreparacion(
        Request $request,
        Pedido $pedido,
        TransicionCumplimiento $transiciones
    ): RedirectResponse {
        $transiciones->registrar(
            $pedido, 'en_preparacion', 'listo_despacho', $request->user()->id,
            ['preparacion_finalizada_por_id' => $request->user()->id,
             'preparacion_finalizada_en' => now()],
            'Preparación finalizada. Pedido listo para despacho.'
        );

        return redirect()->route('cumplimiento.show', $pedido)
            ->with('success', 'Pedido listo para despacho.');
    }

    public function despachar(
        Request $request,
        Pedido $pedido,
        TransicionCumplimiento $transiciones
    ): RedirectResponse {
        $datos = $request->validate([
            'observacion_despacho' => ['nullable', 'string', 'max:1000'],
        ]);

        $observacion = trim((string) ($datos['observacion_despacho'] ?? ''));
        $transiciones->registrar(
            $pedido, 'listo_despacho', 'despachado', $request->user()->id,
            ['despachado_por_id' => $request->user()->id,
             'despachado_en' => now(),
             'observacion_despacho' => $observacion !== '' ? $observacion : null],
            $observacion !== '' ? $observacion : 'Pedido despachado.'
        );

        return redirect()->route('cumplimiento.show', $pedido)
            ->with('success', 'Se registró el despacho del pedido.');
    }

    public function entregar(
        Request $request,
        Pedido $pedido,
        TransicionCumplimiento $transiciones
    ): RedirectResponse {
        $datos = $request->validate([
            'observacion_entrega' => ['nullable', 'string', 'max:1000'],
        ]);

        $observacion = trim((string) ($datos['observacion_entrega'] ?? ''));
        $transiciones->registrar(
            $pedido, 'despachado', 'entregado', $request->user()->id,
            ['entregado_por_id' => $request->user()->id,
             'entregado_en' => now(),
             'observacion_entrega' => $observacion !== '' ? $observacion : null],
            $observacion !== '' ? $observacion : 'Entrega confirmada.'
        );

        return redirect()->route('cumplimiento.show', $pedido)
            ->with('success', 'Entrega confirmada correctamente.');
    }

}
