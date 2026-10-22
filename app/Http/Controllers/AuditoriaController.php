<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $reglasHasta = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('desde')) {
            $reglasHasta[] = 'after_or_equal:desde';
        }
        $filtros = $request->validate(['usuario_id' => ['nullable', 'integer'], 'accion' => ['nullable', 'string', 'max:100'], 'entidad' => ['nullable', 'string', 'max:80'], 'desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => $reglasHasta]);
        $consulta = Auditoria::query();
        foreach (['usuario_id', 'accion', 'entidad'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (! empty($filtros['desde'])) {
            $consulta->where('created_at', '>=', $filtros['desde'].' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $consulta->where('created_at', '<', CarbonImmutable::parse($filtros['hasta'], 'America/Lima')->addDay()->startOfDay());
        }

        return view('auditoria.index', ['registros' => $consulta->orderByDesc('id')->paginate(20)->withQueryString()]);
    }
}
