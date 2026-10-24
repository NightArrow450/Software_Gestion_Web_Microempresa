<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\PresentacionProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockInventarioController extends Controller
{
    private function consultaProductos()
    {
        return PresentacionProducto::query()
            ->join('productos', 'productos.id', '=', 'presentaciones_producto.producto_id')
            ->leftJoin('inventarios', 'inventarios.presentacion_producto_id', '=', 'presentaciones_producto.id')
            ->where('presentaciones_producto.estado', true)
            ->where('productos.estado', true)
            ->where(function ($q) {
                $q->whereNull('presentaciones_producto.variante_producto_id')
                    ->orWhereHas('variante', fn ($v) => $v->where('estado', true));
            });
    }

    public function index(Request $request): View
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'filtro' => ['nullable', Rule::in(['agotado', 'bajo', 'normal'])],
        ]);
        $buscar = trim($datos['buscar'] ?? '');
        $filtro = $datos['filtro'] ?? '';
        $stock = 'COALESCE(inventarios.cantidad_actual, 0)';
        $minimo = 'COALESCE(inventarios.stock_minimo, 10)';

        $productos = $this->consultaProductos()->selectRaw("SUM(CASE WHEN {$stock} = 0 THEN 1 ELSE 0 END) AS agotado, SUM(CASE WHEN {$stock} > 0 AND {$stock} <= {$minimo} THEN 1 ELSE 0 END) AS bajo, SUM(CASE WHEN {$stock} > 0 AND {$stock} > {$minimo} THEN 1 ELSE 0 END) AS normal")->toBase()->first();
        $materiales = Insumo::where('estado', true)->selectRaw('SUM(CASE WHEN cantidad_actual = 0 THEN 1 ELSE 0 END) AS agotado, SUM(CASE WHEN cantidad_actual > 0 AND cantidad_actual <= stock_minimo THEN 1 ELSE 0 END) AS bajo, SUM(CASE WHEN cantidad_actual > 0 AND cantidad_actual > stock_minimo THEN 1 ELSE 0 END) AS normal')->toBase()->first();
        $resumen = [];
        foreach (['agotado', 'bajo', 'normal'] as $estado) {
            $resumen[$estado] = (int) $productos->{$estado} + (int) $materiales->{$estado};
        }

        $consultaProductos = $this->consultaProductos()
            ->with(['producto', 'variante'])
            ->select('presentaciones_producto.*')
            ->addSelect(DB::raw("{$stock} AS stock_actual"))
            ->addSelect(DB::raw("{$minimo} AS stock_minimo_configurado"))
            ->when($buscar !== '', fn ($q) => $q->where(function ($w) use ($buscar) {
                $w->where('presentaciones_producto.codigo_sku', 'like', "%{$buscar}%")
                    ->orWhere('productos.nombre', 'like', "%{$buscar}%");
            }));

        $consultaInsumos = Insumo::where('estado', true)
            ->when($buscar !== '', fn ($q) => $q->where(function ($w) use ($buscar) {
                $w->where('codigo', 'like', "%{$buscar}%")
                    ->orWhere('nombre', 'like', "%{$buscar}%")
                    ->orWhere('categoria', 'like', "%{$buscar}%");
            }));

        if ($filtro === 'agotado') {
            $consultaProductos->whereRaw("{$stock} = 0");
            $consultaInsumos->where('cantidad_actual', 0);
        } elseif ($filtro === 'bajo') {
            $consultaProductos->whereRaw("{$stock} > 0 AND {$stock} <= {$minimo}");
            $consultaInsumos->where('cantidad_actual', '>', 0)
                ->whereColumn('cantidad_actual', '<=', 'stock_minimo');
        } elseif ($filtro === 'normal') {
            $consultaProductos->whereRaw("{$stock} > 0 AND {$stock} > {$minimo}");
            $consultaInsumos->where('cantidad_actual', '>', 0)
                ->whereColumn('cantidad_actual', '>', 'stock_minimo');
        }

        $presentaciones = $consultaProductos->orderBy('presentaciones_producto.codigo_sku')
            ->paginate(15, ['*'], 'productos_pagina')->withQueryString();
        $insumos = $consultaInsumos->orderBy('categoria')->orderBy('nombre')
            ->paginate(15, ['*'], 'insumos_pagina')->withQueryString();
        $puedeConfigurar = $request->user()->role?->tienePermiso('inventario.ajustar') ?? false;

        return view('inventory.stock', compact(
            'resumen', 'presentaciones', 'insumos', 'filtro', 'puedeConfigurar'
        ));
    }

    public function actualizarMinimo(Request $request, PresentacionProducto $presentacion): RedirectResponse
    {
        $datos = $request->validate([
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);
        DB::transaction(function () use ($presentacion, $datos) {
            PresentacionProducto::whereKey($presentacion->id)->lockForUpdate()->firstOrFail();
            $inventario = Inventario::firstOrCreate(
                ['presentacion_producto_id' => $presentacion->id],
                ['cantidad_actual' => 0]
            );
            $inventario->update(['stock_minimo' => (int) $datos['stock_minimo']]);
        }, 3);

        return back()->with('success', 'Nivel mínimo actualizado para '.$presentacion->codigo_sku.'.');
    }

    public function actualizarMinimoInsumo(Request $request, Insumo $insumo): RedirectResponse
    {
        $datos = $request->validate([
            'stock_minimo' => ['required', 'numeric', 'min:0', 'max:1000000000', 'decimal:0,3'],
        ]);
        $insumo->update(['stock_minimo' => $datos['stock_minimo']]);

        return back()->with('success', 'Nivel mínimo actualizado para '.$insumo->codigo.'.');
    }
}
