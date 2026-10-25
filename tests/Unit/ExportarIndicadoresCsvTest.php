<?php

namespace Tests\Unit;

use App\Services\ActividadIndicadores;
use App\Services\ExportarIndicadoresCsv;
use App\Services\FilasIndicadores;
use App\Services\PedidosIndicadores;
use PHPUnit\Framework\TestCase;

class ExportarIndicadoresCsvTest extends TestCase
{
    public function test_caracteres_invisibles_unicode_no_ocultan_una_formula(): void
    {
        foreach (["\u{FEFF}=1", "\u{200B}@SUM(1)", "\u{00A0}+1", "\u{2060}－1"] as $texto) {
            $this->assertSame("'".$texto, ExportarIndicadoresCsv::texto($texto));
        }
    }

    public function test_un_campo_numerico_no_convierte_una_formula_en_numero_confiable(): void
    {
        $csv = new ExportarIndicadoresCsv(new FilasIndicadores(new PedidosIndicadores, new ActividadIndicadores));
        $this->assertSame("'=1+1", $csv->celda('=1+1', 'importe_centimos'));
        $this->assertSame("'+cmd", $csv->celda('+cmd', 'presentacion_id'));
        $this->assertSame('-125', $csv->celda(-125, 'variacion'));
    }

    public function test_ausencia_cero_y_texto_de_sku_conservan_su_significado(): void
    {
        $csv = new ExportarIndicadoresCsv(new FilasIndicadores(new PedidosIndicadores, new ActividadIndicadores));
        $this->assertSame('No disponible', $csv->celda(null, 'cantidad'));
        $this->assertSame('0', $csv->celda(0, 'cantidad'));
        $this->assertSame('0012', $csv->celda('0012', 'sku'));
        $this->assertSame('demostración; "Alfa"', $csv->celda('demostración; "Alfa"', 'descripcion'));
    }
}
