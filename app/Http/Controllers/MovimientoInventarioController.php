<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\PresentacionProducto;
use App\Services\RegistroAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MovimientoInventarioController extends Controller
{
    private const PERMISOS = [
        'entrada' => 'inventario.entrada',
        'salida' => 'inventario.salida',
        'ajuste' => 'inventario.ajustar',
    ];

    public function index(Request $request): View
    {
        $datos = $request->validate([
            'tipo' => ['nullable', Rule::in(['entrada', 'salida', 'ajuste'])],
            'buscar' => ['nullable', 'string', 'max:100'],
            'fecha' => ['nullable', 'date'],
        ]);

        $movimientos = MovimientoInventario::with(['presentacion.producto', 'insumo', 'usuario'])
            ->when(! empty($datos['tipo']), fn ($q) => $q->where('tipo', $datos['tipo']))
            ->when(! empty($datos['fecha']), fn ($q) => $q->whereDate('created_at', $datos['fecha']))
            ->when(! empty($datos['buscar']), function ($q) use ($datos) {
                $buscar = trim($datos['buscar']);
                $q->where(function ($q) use ($buscar) {
                    $q->whereHas('presentacion', function ($p) use ($buscar) {
                        $p->where('codigo_sku', 'like', "%{$buscar}%")
                            ->orWhereHas('producto', fn ($producto) => $producto
                                ->where('nombre', 'like', "%{$buscar}%"));
                    })->orWhereHas('insumo', fn ($i) => $i
                        ->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->orderByDesc('id')->paginate(15)->withQueryString();

        $permisos = $request->user()->role?->permisos?->pluck('nombre')->all() ?? [];
        $puedeRegistrar = count(array_intersect(array_values(self::PERMISOS), $permisos)) > 0;

        $pedidosPorDespacho = DB::table('pedido_despachos')->whereIn('id', $movimientos->pluck('despacho_unidades_id')->filter())->pluck('pedido_id', 'id');

        return view('inventory.movements.index', compact('movimientos', 'puedeRegistrar', 'pedidosPorDespacho'));
    }

    public function create(Request $request): View
    {
        $permisos = $request->user()->role?->permisos?->pluck('nombre')->all() ?? [];
        $tiposDisponibles = array_filter(self::PERMISOS, fn ($p) => in_array($p, $permisos, true));
        abort_if(empty($tiposDisponibles), 403);

        $presentaciones = PresentacionProducto::with('producto')
            ->where('estado', true)
            ->whereHas('producto', fn ($q) => $q->where('estado', true))
            ->where(function ($q) {
                $q->whereNull('variante_producto_id')
                    ->orWhereHas('variante', fn ($v) => $v->where('estado', true));
            })->orderBy('codigo_sku')->get();

        $existencias = Inventario::whereIn('presentacion_producto_id', $presentaciones->pluck('id'))
            ->pluck('cantidad_actual', 'presentacion_producto_id');

        $insumos = Insumo::where('estado', true)->orderBy('categoria')->orderBy('nombre')->get();

        return view('inventory.movements.create', compact(
            'presentaciones', 'existencias', 'insumos', 'tiposDisponibles'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'presentacion_producto_id' => ['nullable', 'integer', 'exists:presentaciones_producto,id'],
            'insumo_id' => ['nullable', 'integer', 'exists:insumos,id'],
            'tipo' => ['required', Rule::in(array_keys(self::PERMISOS))],
            'cantidad' => ['required', 'numeric', 'min:0', 'max:1000000000', 'decimal:0,3'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $esProducto = ! empty($datos['presentacion_producto_id']);
        $esInsumo = ! empty($datos['insumo_id']);
        if ($esProducto === $esInsumo) {
            throw ValidationException::withMessages([
                'insumo_id' => 'Selecciona solo un producto terminado O un insumo.',
            ]);
        }

        $usuario = $request->user();
        abort_unless($usuario->role?->tienePermiso(self::PERMISOS[$datos['tipo']]), 403);

        // Milésimas enteras: evita diferencias de redondeo con litros/kilos.
        $cantidadMil = (int) round((float) $datos['cantidad'] * 1000);
        if ($datos['tipo'] !== 'ajuste' && $cantidadMil < 1) {
            throw ValidationException::withMessages(['cantidad' => 'Entradas y salidas requieren cantidad mayor que cero.']);
        }
        if ($esProducto && $cantidadMil % 1000 !== 0) {
            throw ValidationException::withMessages(['cantidad' => 'Los productos terminados se controlan en unidades enteras.']);
        }

        DB::transaction(function () use ($datos, $usuario, $esProducto, $cantidadMil) {
            if ($esProducto) {
                $presentacion = PresentacionProducto::whereKey($datos['presentacion_producto_id'])
                    ->lockForUpdate()->firstOrFail();
                if (! $presentacion->estado || ! $presentacion->producto?->estado ||
                    ($presentacion->variante && ! $presentacion->variante->estado)) {
                    throw ValidationException::withMessages([
                        'presentacion_producto_id' => 'La presentación está inactiva.',
                    ]);
                }
                // Bloqueamos la presentación para serializar la creación inicial del inventario.
                Inventario::firstOrCreate(
                    ['presentacion_producto_id' => $presentacion->id],
                    ['cantidad_actual' => 0]
                );
                $item = Inventario::where('presentacion_producto_id', $presentacion->id)
                    ->lockForUpdate()->firstOrFail();
                $anteriorMil = (int) $item->cantidad_actual * 1000;
            } else {
                $item = Insumo::whereKey($datos['insumo_id'])->lockForUpdate()->firstOrFail();
                if (! $item->estado) {
                    throw ValidationException::withMessages(['insumo_id' => 'El insumo está inactivo.']);
                }
                $anteriorMil = (int) round((float) $item->cantidad_actual * 1000);
            }

            $nuevoMil = match ($datos['tipo']) {
                'entrada' => $anteriorMil + $cantidadMil,
                'salida' => $anteriorMil - $cantidadMil,
                'ajuste' => $cantidadMil, // Conteo físico final.
                default => throw new \LogicException('Tipo de movimiento no válido tras la validación.'),
            };
            if ($nuevoMil < 0) {
                throw ValidationException::withMessages([
                    'cantidad' => 'Stock insuficiente: disponible '.number_format($anteriorMil / 1000, 3, '.', '').'.',
                ]);
            }
            if ($nuevoMil > ($esProducto ? 4294967295 * 1000 : 1000000000 * 1000)) {
                throw ValidationException::withMessages(['cantidad' => 'La existencia máxima fue superada.']);
            }
            if ($nuevoMil === $anteriorMil) {
                throw ValidationException::withMessages(['cantidad' => 'El movimiento no modifica el stock.']);
            }

            $item->update([
                'cantidad_actual' => $esProducto
                    ? intdiv($nuevoMil, 1000)
                    : number_format($nuevoMil / 1000, 3, '.', ''),
            ]);

            $movimiento = MovimientoInventario::create([
                'presentacion_producto_id' => $esProducto ? $datos['presentacion_producto_id'] : null,
                'insumo_id' => $esProducto ? null : $datos['insumo_id'],
                'usuario_id' => $usuario->id,
                'tipo' => $datos['tipo'],
                'cantidad' => number_format(abs($nuevoMil - $anteriorMil) / 1000, 3, '.', ''),
                'stock_anterior' => number_format($anteriorMil / 1000, 3, '.', ''),
                'stock_nuevo' => number_format($nuevoMil / 1000, 3, '.', ''),
                'motivo' => trim($datos['motivo']),
            ]);
            RegistroAuditoria::registrar('stock_'.$datos['tipo'], $esProducto ? 'presentacion_producto' : 'insumo', (int) ($datos['presentacion_producto_id'] ?? $datos['insumo_id']), [
                'movimiento_id' => $movimiento->id, 'stock_anterior' => $movimiento->stock_anterior,
                'stock_nuevo' => $movimiento->stock_nuevo, 'motivo' => $movimiento->motivo,
            ]);

        }, 3);

        return redirect()->route('inventario.movimientos.index')
            ->with('success', 'Movimiento registrado con trazabilidad y cantidad actualizada.');
    }
}
