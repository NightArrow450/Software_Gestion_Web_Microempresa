<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Services\AtenderPedidoUnidades;
use App\Services\BusquedaCatalogo;
use App\Services\GestionarPedidoUnidades;
use App\Services\LecturaPedido;
use App\Services\LeerAgrupaciones;
use App\Services\RetirarFaltantesPedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            $previas = array_slice(array_values(array_filter(old('detalles'), 'is_array')), 0, 18);
            $skusPrevios = PresentacionProducto::with(['producto', 'variante'])->whereIn('id', array_column($previas, 'presentacion_producto_id'))->get()->keyBy('id');
            $lineas = array_map(function (array $fila) use ($skusPrevios, $pedido): array {
                $sku = $skusPrevios->get((int) ($fila['presentacion_producto_id'] ?? 0));
                $historica = $pedido?->detalles->firstWhere('id', (int) ($fila['id'] ?? 0));
                if ($sku) {
                    $fila['precio_unitario'] = $historica && $historica->presentacion_producto_id === $sku->id ? $historica->precio_unitario : $sku->precio_unitario;
                    $fila['nombre'] = $historica->descripcion_producto ?? app(LecturaPedido::class)->nombre($sku);
                    $fila['sku'] = $sku->codigo_sku;
                    $fila['compatibilidad'] = [$sku->producto_id, (string) $sku->contenido, $sku->unidad_medida, $sku->tipo_envase];
                }

                return $fila;
            }, $previas);
        }

        $agrupacionesIniciales = old('agrupaciones_json') ? json_decode(old('agrupaciones_json'), true) : ($foto['agrupaciones'] ?? []);
        $agrupacionesIniciales = is_array($agrupacionesIniciales) && array_is_list($agrupacionesIniciales)
            ? array_values(array_filter($agrupacionesIniciales, static fn ($grupo): bool => is_array($grupo)
                && isset($grupo['capacidad']) && is_array($grupo['componentes'] ?? null)
                && array_is_list($grupo['componentes']) && collect($grupo['componentes'])->every(static fn ($c): bool => is_array($c) && isset($c['sku_id'], $c['cantidad']))))
            : [];

        $productoSeleccionado = old('producto_seleccionado') ? Producto::where('estado', true)->find((int) old('producto_seleccionado')) : null;
        $seleccionSku = null;
        if ($productoSeleccionado && old('sku_seleccionado')) {
            $consulta = PresentacionProducto::with(['producto', 'variante'])->where('producto_id', $productoSeleccionado->id)->whereKey((int) old('sku_seleccionado'));
            BusquedaCatalogo::vendibles($consulta);
            $sku = $consulta->first();
            if ($sku) {
                $seleccionSku = ['id' => $sku->id, 'sku' => $sku->codigo_sku, 'producto' => $sku->producto->nombre, 'variante_id' => $sku->variante_producto_id,
                    'variante' => $sku->variante?->valor, 'presentacion' => $sku->contenido.' '.$sku->unidad_medida.' · '.$sku->tipo_envase,
                    'precio_unitario' => $sku->precio_unitario, 'compatibilidad' => [$sku->producto_id, (string) $sku->contenido, $sku->unidad_medida, $sku->tipo_envase]];
            }
        }

        return view('orders.units-form', compact('productoSeleccionado', 'seleccionSku', 'pedido', 'cliente', 'lineas', 'foto', 'agrupacionesIniciales'));
    }

    public function catalogo(Request $request): JsonResponse
    {
        $rol = $request->user()->role;
        abort_unless($rol?->tienePermiso('pedidos.crear') || $rol?->tienePermiso('pedidos.editar'), 403);
        $datos = $request->validate(['tipo' => ['sometimes', 'in:clientes,productos,skus'], 'q' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'], 'producto_id' => ['nullable', 'integer', 'min:1']]);
        $tipo = $datos['tipo'] ?? 'productos';
        $termino = trim($datos['q'] ?? '');
        if ($tipo === 'clientes') {
            $clientes = Cliente::where('estado', true);
            BusquedaCatalogo::coincidencia($clientes, ['nombre', 'documento', 'telefono'], $termino);

            return response()->json($clientes->orderBy('nombre')->orderBy('id')->paginate(20, ['id', 'nombre']));
        }
        if ($tipo === 'productos') {
            $productos = Producto::where('estado', true)->whereHas('presentaciones', fn ($q) => BusquedaCatalogo::vendibles($q));
            if ($termino !== '') {
                $productos->where(function ($q) use ($termino): void {
                    $q->where(fn ($n) => BusquedaCatalogo::coincidencia($n, ['productos.nombre', 'productos.codigo'], $termino))
                        ->orWhereHas('presentaciones', function ($sku) use ($termino): void {
                            BusquedaCatalogo::vendibles($sku);
                            BusquedaCatalogo::coincidencia($sku, ['codigo_sku'], $termino);
                        });
                });
            }

            return response()->json($productos->orderBy('nombre')->orderBy('id')->paginate(20, ['id', 'nombre']));
        }
        $request->validate(['producto_id' => ['required', 'integer', 'min:1']]);
        $skus = PresentacionProducto::with(['producto', 'variante'])->where('producto_id', $datos['producto_id']);
        BusquedaCatalogo::vendibles($skus);
        $skus = $skus->orderBy('codigo_sku')->orderBy('id')->paginate(50);
        $skus->through(fn ($s): array => ['id' => $s->id, 'sku' => $s->codigo_sku, 'producto' => $s->producto->nombre, 'variante_id' => $s->variante_producto_id,
            'variante' => $s->variante?->valor, 'presentacion' => $s->contenido.' '.$s->unidad_medida.' · '.$s->tipo_envase, 'precio_unitario' => $s->precio_unitario,
            'compatibilidad' => [$s->producto_id, (string) $s->contenido, $s->unidad_medida, $s->tipo_envase]]);

        return response()->json($skus);
    }

    public function retirarFaltantes(Request $request, Pedido $pedido, RetirarFaltantesPedido $servicio): RedirectResponse
    {
        $servicio->ejecutar($pedido, $request->all(), $request->user());

        return redirect()->route('pedidos.show', $pedido)->with('success', 'Faltantes retirados del compromiso comercial. Se conservaron el pedido original y todas las entregas; no se modificó el inventario.');
    }

    public function guardar(Request $request, ?Pedido $pedido = null): RedirectResponse
    {
        $request->validate(['agrupaciones_json' => ['nullable', 'json', 'max:1000000']]);
        $entrada = $request->all();
        try {
            $entrada['agrupaciones'] = LeerAgrupaciones::leer($request->input('agrupaciones_json'));
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['agrupaciones' => $error->getMessage()]);
        }
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
