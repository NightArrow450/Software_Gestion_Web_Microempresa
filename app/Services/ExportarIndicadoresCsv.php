<?php

namespace App\Services;

class ExportarIndicadoresCsv
{
    public function __construct(private readonly FilasIndicadores $fuente) {}

    /** @param resource $salida */
    public function escribir(mixed $salida, array $f, string $generado): void
    {
        fwrite($salida, "\xEF\xBB\xBF");
        if (! empty($f['resumen'])) {
            $resumen = app(ResumenIndicadores::class)->pagina($f);
            fputcsv($salida, ['indicador', 'valor', 'referencias_no_disponibles', 'periodo_desde', 'periodo_hasta', 'generado_en_lima', 'alcance'], ';', '"', '', "\r\n");
            foreach (['filas_filtradas' => $resumen['cantidad'], ...$resumen['comercial'], ...$resumen['totales']] as $k => $v) {
                fputcsv($salida, [$k, $v ?? 'No disponible', $resumen['noDisponibles'][$k] ?? 0, $f['desde'] ?? '', $f['hasta'], $generado, self::texto(FilasIndicadores::REPORTES[$f['reporte']][1])], ';', '"', '', "\r\n");
            }

            return;
        }
        $campos = $this->fuente->campos($f);
        fputcsv($salida, [...$campos, 'periodo_desde', 'periodo_hasta', 'generado_en_lima', 'alcance'], ';', '"', '', "\r\n");
        foreach ($this->fuente->filas($f) as $fila) {
            $celdas = [];
            foreach ($campos as $c) {
                $celdas[] = $this->celda($fila[$c] ?? null, $c);
            }
            fputcsv($salida, [...$celdas, $f['desde'] ?? '', $f['hasta'], $generado, self::texto(FilasIndicadores::REPORTES[$f['reporte']][1])], ';', '"', '', "\r\n");
        }
    }

    public function celda(string|int|float|null $valor, string $campo): string
    {
        if ($valor === null) {
            return 'No disponible';
        }
        $numerico = str_ends_with($campo, '_id') || str_ends_with($campo, '_centimos') || in_array($campo, ['version', 'numero_paquete', 'tamano', 'cantidad_comercial', 'paquetes_definidos', 'paquetes_pendientes', 'unidades_fisicas', 'unidades_sin_composicion', 'unidades_sin_distribuir', 'solicitadas', 'stock_actual', 'faltante_conocido', 'despachadas', 'aceptadas', 'rechazadas', 'pendientes', 'recibidas', 'aptas', 'danadas', 'en_revision', 'por_revisar', 'no_recuperadas', 'reincorporadas', 'por_retornar', 'por_aprobar', 'envasadas_brutas', 'correcciones_administrativas', 'perdidas_fisicas', 'efecto_neto', 'neto_actual_envasado', 'consumo_bruto', 'devuelto', 'perdido', 'salida_neta', 'cantidad', 'stock_anterior', 'stock_nuevo', 'saldo_inicial', 'saldo_final', 'variacion'], true);
        if ($numerico && preg_match('/\A-?\d+(?:\.\d+)?\z/', (string) $valor)) {
            return (string) $valor;
        }

        return self::texto((string) $valor);
    }

    public static function texto(string $valor): string
    {
        $inicio = preg_replace('/\A[\s\p{Z}\p{Cf}]*/u', '', $valor) ?? $valor;
        if (preg_match('/\A[=+\-@＝＋－＠]/u', $inicio) || preg_match('/\A[\t\r\n]/', $valor)) {
            return "'".$valor;
        }

        return $valor;
    }
}
