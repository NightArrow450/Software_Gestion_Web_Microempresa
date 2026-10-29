<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Services\AtenderPedidoUnidades;
use App\Services\GestionarPedidoUnidades;
use App\Services\GestionarSurtidos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PedidoUnidadesController extends Controller
{
    public function formulario(?Pedido $pedido = null): View
    {
        $pedido?->load('detalles.presentacion.producto', 'detalles.presentacion.variante');
        $clienteId = old('cliente_id') ?? $pedido?->cliente_id;
        $cliente = $clienteId ? Cliente::find((int) $clienteId) : null;
        $version = $pedido ? DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->orderByDesc('numero')->first() : null;
        $foto = $version ? json_decode($version->datos, true, 512, JSON_THROW_ON_ERROR) : [];
        $lineas = $pedido ? $pedido->detalles->map(fn ($d): array => ['id' => $d->id, 'presentacion_producto_id' => $d->presentacion_producto_id, 'nombre' => $d->descripcion_producto, 'sku' => $d->codigo_sku, 'precio_unitario' => $d->precio_unitario, 'cantidad' => $d->cantidad, 'cantidad_espera' => $d->cantidad_espera,
            'compatibilidad' => [$d->presentacion->producto_id, (string) $d->presentacion->contenido, $d->presentacion->unidad_medida, $d->presentacion->tipo_envase]])->values()->all() : [];
        if (is_array(old('detalles'))) {
            $previas = array_slice(old('detalles'), 0, 18);
            $skusPrevios = PresentacionProducto::with(['producto', 'variante'])->whereIn('id', array_column($previas, 'presentacion_producto_id'))->get()->keyBy('id');
            $lineas = array_map(function (array $fila) use ($skusPrevios, $pedido): array {
                $sku = $skusPrevios->get((int) ($fila['presentacion_producto_id'] ?? 0));
                $historica = $pedido?->detalles->firstWhere('id', (int) ($fila['id'] ?? 0));
                if ($sku) {
                    $fila['precio_unitario'] = $historica && $historica->presentacion_producto_id === $sku->id ? $historica->precio_unitario : $sku->precio_unitario;
                    $fila['nombre'] = $historica->descripcion_producto ?? app(GestionarSurtidos::class)->nombre($sku);
                    $fila['sku'] = $sku->codigo_sku;
                    $fila['compatibilidad'] = [$sku->producto_id, (string) $sku->contenido, $sku->unidad_medida, $sku->tipo_envase];
                }

                return $fila;
            }, $previas);
        }

        return view('orders.units-form', compact('pedido', 'cliente', 'lineas', 'foto'));
    }

    public function catalogo(Request $request): JsonResponse
    {
        $rol = $request->user()->role;
        abort_unless($rol?->tienePermiso('pedidos.crear') || $rol?->tienePermiso('pedidos.editar'), 403);
        $datos = $request->validate(['tipo' => ['sometimes', 'in:clientes,productos,skus'], 'q' => ['nullable', 'string', 'max:100'], 'producto_id' => ['nullable', 'integer', 'min:1']]);
        $datos['tipo'] ??= 'productos';
        $termino = trim($datos['q'] ?? '');
        if ($datos['tipo'] === 'clientes') {
            return response()->json(Cliente::where('estado', true)->when($termino !== '', fn ($q) => $q->where('nombre', 'like', '%'.$termino.'%'))->orderBy('nombre')->orderBy('id')->paginate(20, ['id', 'nombre']));
        }
        if ($datos['tipo'] === 'productos') {
            return response()->json(Producto::where('estado', true)->when($termino !== '', fn ($q) => $q->where('nombre', 'like', '%'.$termino.'%'))->orderBy('nombre')->orderBy('id')->paginate(20, ['id', 'nombre']));
        }
        $request->validate(['producto_id' => ['required', 'integer', 'min:1']]);
        $skus = PresentacionProducto::with(['producto', 'variante'])->where('producto_id', $datos['producto_id'])->where('estado', true)
            ->whereHas('producto', fn ($q) => $q->where('estado', true))->where(fn ($q) => $q->whereNull('variante_producto_id')->orWhereHas('variante', fn ($v) => $v->where('estado', true)))
            ->orderBy('codigo_sku')->paginate(50);
        $skus->through(fn ($s): array => ['id' => $s->id, 'sku' => $s->codigo_sku, 'producto' => $s->producto->nombre, 'variante_id' => $s->variante_producto_id,
            'variante' => $s->variante?->valor, 'presentacion' => $s->contenido.' '.$s->unidad_medida.' · '.$s->tipo_envase, 'precio_unitario' => $s->precio_unitario,
            'compatibilidad' => [$s->producto_id, (string) $s->contenido, $s->unidad_medida, $s->tipo_envase]]);

        return response()->json($skus);
    }

    public function guardar(Request $request, ?Pedido $pedido = null): RedirectResponse
    {
        $request->validate(['agrupaciones_json' => ['nullable', 'json', 'max:1000000']]);
        $entrada = $request->all();
        $entrada['agrupaciones'] = json_decode($request->input('agrupaciones_json', '[]') ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $guardado = app(GestionarPedidoUnidades::class)->guardar($entrada, $request->user(), $pedido);

        return redirect()->route('pedidos.show', $guardado)->with('success', 'Pedido por unidades guardado con su agrupación e historial. No se comprobó líquido ni se descontó inventario.');
    }

    public function preparar(Request $request, Pedido $pedido, AtenderPedidoUnidades $servicio): RedirectResponse
    {
        $servicio->preparar($pedido, $request->all(), $request->user());

        return redirect()->route('cumplimiento.show', $pedido)->with('success', 'Preparación física e incidencia registradas sin crear ni descontar inventario.');
    }

    public function despachar(Request $request, Pedido $pedido, AtenderPedidoUnidades $servicio): RedirectResponse
    {
        $servicio->despachar($pedido, $request->all(), $request->user());

        return redirect()->route('cumplimiento.show', $pedido)->with('success', 'Despacho físico registrado una sola vez; las cantidades restantes se conservan.');
    }
}
