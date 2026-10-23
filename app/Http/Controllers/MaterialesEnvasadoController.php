<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEnvasado;
use App\Models\Insumo;
use App\Models\PresentacionProducto;
use App\Services\ConfigurarMaterialesEnvasado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialesEnvasadoController extends Controller
{
    public function index(PresentacionProducto $presentacion): View
    {
        $presentacion->load('producto');
        $configuraciones = ConfiguracionEnvasado::with('materiales.insumo')->where('presentacion_producto_id', $presentacion->id)->orderBy('id')->get();
        $insumos = Insumo::where('estado', true)->where('categoria', '<>', 'Químicos')->whereIn('unidad_medida', ['unidad', 'metro', 'paquete', 'kilogramo'])->orderBy('nombre')->get();

        return view('envasado.materiales', compact('presentacion', 'configuraciones', 'insumos'));
    }

    public function store(Request $request, PresentacionProducto $presentacion, ConfigurarMaterialesEnvasado $servicio): RedirectResponse
    {
        $servicio->guardar($presentacion, $request->all());

        return redirect()->route('envasado.materiales', $presentacion)->with('success', 'Alternativa de materiales guardada.');
    }

    public function update(Request $request, PresentacionProducto $presentacion, ConfiguracionEnvasado $configuracion, ConfigurarMaterialesEnvasado $servicio): RedirectResponse
    {
        $servicio->guardar($presentacion, $request->all(), $configuracion);

        return redirect()->route('envasado.materiales', $presentacion)->with('success','Configuración actualizada. Los consumos históricos se conservan.');
    }
}
