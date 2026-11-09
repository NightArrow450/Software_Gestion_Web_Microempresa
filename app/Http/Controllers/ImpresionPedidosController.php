<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Corrección posterior al Sprint 2: impresión masiva por fecha.
 * Se mantiene separado de PedidoController para no modificar HU-01/HU-02/HU-03.
 */
class ImpresionPedidosController extends Controller
{
    /**
     * Imprime TODOS los pedidos no cancelados de una fecha elegida.
     * La vista distribuye dos notas por cada hoja A4 horizontal.
     */
    public function porFecha(Request $request): View
    {
        $datos = $request->validate([
            'fecha_pedido' => ['required', 'date_format:Y-m-d'],
        ]);

        $pedidos = Pedido::with(['vendedor', 'detalles.presentacion'])
            ->whereDate('fecha_pedido', $datos['fecha_pedido'])
            ->where('estado', '!=', 'cancelado')
            ->orderBy('id')
            ->get();

        if ($pedidos->isEmpty()) {
            throw ValidationException::withMessages([
                'fecha_pedido' => 'No hay pedidos imprimibles en la fecha seleccionada. Los pedidos cancelados no se imprimen.',
            ]);
        }

        return view('orders.print-two', [
            'pedidos' => $pedidos,
            'fechaImpresion' => $datos['fecha_pedido'],
        ]);
    }

    /**
     * Compatibilidad con HU-01: conserva la URL antigua para dos pedidos.
     * NUNCA permite imprimir pedidos cancelados, ni mediante enlace directo.
     */
    public function imprimirDos(Request $request): View
    {
        $datos = $request->validate([
            'pedido_1' => ['required', 'integer', 'exists:pedidos,id'],
            'pedido_2' => ['required', 'integer', 'exists:pedidos,id', 'different:pedido_1'],
        ]);

        $pedidosEncontrados = Pedido::with(['vendedor', 'detalles.presentacion'])
            ->whereIn('id', [$datos['pedido_1'], $datos['pedido_2']])
            ->where('estado', '!=', 'cancelado')
            ->get()
            ->keyBy('id');

        if ($pedidosEncontrados->count() !== 2) {
            throw ValidationException::withMessages([
                'pedidos' => 'No se pueden imprimir pedidos cancelados.',
            ]);
        }

        // Conservar el orden solicitado, no el orden de la base de datos.
        $pedidos = collect([
            $pedidosEncontrados[$datos['pedido_1']],
            $pedidosEncontrados[$datos['pedido_2']],
        ]);

        return view('orders.print-two', [
            'pedidos' => $pedidos,
            'fechaImpresion' => null,
        ]);
    }
}
