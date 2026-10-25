<?php

namespace Tests\Unit;

use App\Services\CantidadesEnvasado;
use PHPUnit\Framework\TestCase;

class CantidadesEnvasadoTest extends TestCase
{
    public function test_convierte_fracciones_sin_redondear_el_consumo(): void
    {
        $this->assertSame(125, CantidadesEnvasado::milesimas('0.125'));
        $this->assertSame(1200, CantidadesEnvasado::milesimas('1.2'));
        $this->assertSame(1230, CantidadesEnvasado::milesimas('1.23'));
    }

    public function test_decimal_conserva_tres_posiciones_incluido_el_cero(): void
    {
        $this->assertSame('0.000', CantidadesEnvasado::decimal(0));
        $this->assertSame('0.001', CantidadesEnvasado::decimal(1));
        $this->assertSame('12.050', CantidadesEnvasado::decimal(12050));
    }

    public function test_limite_del_conversor_no_pierde_digitos_en_php_de_64_bits(): void
    {
        $this->assertSame(9999999999999, CantidadesEnvasado::milesimas('9999999999.999'));
        $this->assertSame('9999999999.999', CantidadesEnvasado::decimal(9999999999999));
    }

    public function test_ida_y_vuelta_conserva_cantidades_validas_sin_exigir_positividad_al_formateador(): void
    {
        foreach ([0, 1, 125, 999, 1000, 99125] as $milesimas) {
            $this->assertSame($milesimas, CantidadesEnvasado::milesimas(CantidadesEnvasado::decimal($milesimas)));
        }
    }
}
