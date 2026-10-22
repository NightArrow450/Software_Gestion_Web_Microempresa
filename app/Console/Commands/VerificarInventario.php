<?php

namespace App\Console\Commands;

use App\Services\VerificadorInventario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerificarInventario extends Command
{
    protected $signature = 'inventario:verificar {--json : Muestra el informe en formato JSON}';

    protected $description = 'Verifica cadenas y saldos de productos e insumos sin corregir datos';

    public function handle(VerificadorInventario $verificador): int
    {
        $reporte = $verificador->revisar();
        Log::channel('seguridad')->info('verificacion_inventario', $reporte);
        if ($this->option('json')) {
            $this->line(json_encode($reporte, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Clasificación', 'Cantidad'], [['Sin historial / saldo inicial', count($reporte['sin_historial'])], ['Saldo inicial o historial previo', count($reporte['saldo_inicial'])], ['Consistentes', count($reporte['consistentes'])], ['Inconsistencias', count($reporte['inconsistencias'])], ['Movimientos huérfanos', count($reporte['huerfanos'])]]);
            foreach (['inconsistencias', 'huerfanos', 'sin_historial', 'saldo_inicial'] as $grupo) {
                foreach ($reporte[$grupo] as $dato) {
                    $this->line(json_encode(['clasificacion' => $grupo, 'detalle' => $dato], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                }
            }
        }

        return $reporte['correcto'] ? self::SUCCESS : self::FAILURE;
    }
}
