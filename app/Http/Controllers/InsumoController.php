<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InsumoController extends Controller
{
    public function create(): View
    {
        return view('inventory.supplies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['codigo' => strtoupper(trim((string) $request->input('codigo', '')))]);
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'unique:insumos,codigo'],
            'nombre' => ['required', 'string', 'max:160'],
            'categoria' => ['required', Rule::in(['Envases', 'Tapas', 'Etiquetas', 'Químicos', 'Otros'])],
            'unidad_medida' => ['required', Rule::in(['unidad', 'litro', 'kilogramo', 'metro', 'paquete'])],
            'cantidad_actual' => ['required', 'numeric', 'min:0', 'max:1000000000', 'decimal:0,3'],
        ]);

        // HU-04: inventario físico inicial. HU-05 auditará los movimientos posteriores.
        $datos['codigo'] = strtoupper(trim($datos['codigo']));
        $datos['nombre'] = trim($datos['nombre']);
        Insumo::create($datos);

        return redirect()->route('inventario.index')
            ->with('success', 'Insumo registrado con su existencia inicial.');
    }
}
