<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Services\AprobarRetornoPedido;
use App\Services\GestionarIncidenciaEntrega;
use App\Services\RegistrarResultadoEntrega;
use App\Services\RegistrarRetornoPedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntregaIncidenciaController extends Controller
{
    public function resultado(Request $request, Pedido $pedido, RegistrarResultadoEntrega $servicio): RedirectResponse
    {
        $servicio->ejecutar($pedido, $request->all(), $request->user());

        return redirect()->route('cumplimiento.show', $pedido)->with('success', 'Resultado de entrega registrado. El rechazo no incrementa existencias.');
    }

    public function rectificar(Request $request, Pedido $pedido, RegistrarResultadoEntrega $servicio): RedirectResponse
    {
        $servicio->ejecutar($pedido, $request->all(), $request->user(), rectificar: true);

        return redirect()->route('cumplimiento.show', $pedido)->with('success', 'Rectificación autorizada registrada sin alterar el historial anterior.');
    }

    public function retorno(Request $request, Pedido $pedido, RegistrarRetornoPedido $servicio): RedirectResponse
    {
        $servicio->ejecutar($pedido, $request->all(), $request->user());

        return redirect()->route('cumplimiento.show', $pedido)->with('success', 'Retorno registrado. Las unidades aptas requieren aprobación para volver al stock.');
    }

    public function aprobar(Request $request, Pedido $pedido, AprobarRetornoPedido $servicio): RedirectResponse
    {
        $servicio->ejecutar($pedido, $request->all(), $request->user());

        return redirect()->route('cumplimiento.show', $pedido)->with('success', 'Reincorporación parcial aprobada y registrada en inventario.');
    }

    public function gestionar(Request $request, Pedido $pedido, GestionarIncidenciaEntrega $servicio): RedirectResponse
    {
        $servicio->ejecutar($pedido, $request->all(), $request->user(), $request->route('accion'));

        return redirect()->route('cumplimiento.show', $pedido)->with('success', 'Revisión del expediente registrada con auditoría.');
    }
}
