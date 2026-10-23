<?php

namespace App\Http\Controllers;

use App\Models\Envasado;
use App\Services\CorregirEnvasado;
use App\Services\RegistroAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CorreccionEnvasadoController extends Controller
{
    public function previsualizar(Request $request, Envasado $envasado, CorregirEnvasado $servicio): View
    {
        $plan = $servicio->previsualizar($envasado, $request->all());

        return view('envasado.confirmar-correccion', compact('plan'));
    }

    public function store(Request $request, Envasado $envasado, CorregirEnvasado $servicio): RedirectResponse
    {
        $servicio->ejecutar($envasado, $request->all(), $request->user());

        return redirect()->route('envasado.show', $envasado)->with('success', 'Compensación registrada. Los movimientos originales se conservaron.');
    }

    public function reportar(Request $request, Envasado $envasado): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($request, $envasado, $datos): void {
            $reporte = $envasado->reportes()->create(['usuario_id' => $request->user()->id, 'motivo' => $datos['motivo']]);
            RegistroAuditoria::registrar('envasado_reportado', 'envasado_reporte', $reporte->id, ['envasado_id' => $envasado->id, 'motivo' => $datos['motivo']]);
        });

        return redirect()->route('envasado.show', $envasado)->with('success','Error comunicado. Este reporte no modifica el inventario.');
    }
}
