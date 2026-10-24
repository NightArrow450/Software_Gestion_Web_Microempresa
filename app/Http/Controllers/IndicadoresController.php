<?php

namespace App\Http\Controllers;

use App\Services\ExportarIndicadoresCsv;
use App\Services\FilasIndicadores;
use App\Services\FiltrosIndicadores;
use App\Services\ResumenIndicadores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IndicadoresController extends Controller
{
    private function filtros(Request $request): array
    {
        $f = FiltrosIndicadores::validar($request->query());
        abort_if($f['economico'] && ! $request->user()->role?->tienePermiso('indicadores.economicos.ver'), 403);

        return $f;
    }

    public function index(Request $request, ResumenIndicadores $servicio, FilasIndicadores $fuente): View
    {
        $filtros = $this->filtros($request);
        $resultado = DB::transaction(fn (): array => $servicio->pagina($filtros));

        return view('indicadores.index', ['filtros' => $filtros, 'resultado' => $resultado, 'campos' => $fuente->campos($filtros), 'reportes' => FilasIndicadores::REPORTES, 'generado' => now('America/Lima')->toDateTimeString()]);
    }

    public function exportar(Request $request, ExportarIndicadoresCsv $servicio): StreamedResponse
    {
        $f = $this->filtros($request);
        $generado = now('America/Lima')->toDateTimeString();
        Log::channel('seguridad')->info('indicadores_exportados', ['usuario_id' => $request->user()->id, 'reporte' => $f['reporte'], 'desde' => $f['desde'], 'hasta' => $f['hasta'], 'economico' => (bool) $f['economico']]);

        return response()->streamDownload(function () use ($servicio, $f, $generado): void {
            $salida = fopen('php://output', 'wb');
            if ($salida === false) {
                throw new \RuntimeException('No se pudo iniciar la exportación.');
            }
            try {
                DB::transaction(fn () => $servicio->escribir($salida, $f, $generado));
            } finally {
                fclose($salida);
            }
        }, 'indicadores_'.$f['reporte'].'_'.str_replace([' ', ':'], ['_', ''], $generado).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
