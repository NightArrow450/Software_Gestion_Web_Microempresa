<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Services\ConfigurarFormatoSurtido;
use App\Services\GestionarSurtidos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SurtidosController extends Controller
{
    public function index(GestionarSurtidos $servicio): View
    {
        abort_unless($servicio->disponible(), 503, 'Primero aplica las migraciones de surtidos con la autorización correspondiente.');
        $formatos = DB::table('formatos_surtidos')->orderBy('nombre')->get();
        $autorizados = DB::table('formato_surtido_skus')->get()->groupBy('formato_surtido_id');
        $productos = Producto::where('estado', true)->orderBy('nombre')->get();
        $skus = PresentacionProducto::with(['producto', 'variante'])->where('estado', true)->orderBy('codigo_sku')->get();

        return view('products.surtidos', compact('formatos', 'autorizados', 'productos', 'skus'));
    }

    public function guardar(Request $request, ConfigurarFormatoSurtido $servicio, ?int $formato = null): RedirectResponse
    {
        $servicio->guardar($request->all(), $request->user(), $formato);

        return redirect()->route('surtidos.index')->with('success', 'Modalidad surtida guardada. Las composiciones se seleccionan dentro de cada pedido.');
    }

    public function componer(Request $request, Pedido $pedido, PedidoDetalle $detalle, GestionarSurtidos $servicio): RedirectResponse
    {
        $servicio->autorizar($request->user());
        $datos = $request->validate(['version' => ['required', 'integer', 'min:0'], 'paquetes_json' => ['required', 'string', 'max:3000000'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($pedido, $detalle, $servicio, $datos, $request): void {
            $actual = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            if ($detalle->pedido_id !== $pedido->id || (int) $actual->version !== (int) $datos['version']) {
                throw ValidationException::withMessages(['version' => 'El pedido cambió o la línea no pertenece al pedido. Recarga antes de guardar.']);
            }
            $servicio->guardar($detalle, $servicio->leerPaquetes($datos['paquetes_json']), $request->user(), $datos['motivo']);
            DB::table('pedidos')->where('id', $pedido->id)->update(['version' => DB::raw('version + 1'), 'updated_at' => now()]);
        }, 3);

        return redirect()->route('pedidos.show', $pedido)->with('success', 'Composición actualizada con historial; no se reservaron ni descontaron existencias.');
    }
}
