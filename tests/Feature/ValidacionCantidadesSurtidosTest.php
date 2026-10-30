<?php

namespace Tests\Feature;

use App\Services\CantidadesEnvasado;
use App\Services\LeerAgrupaciones;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidacionCantidadesSurtidosTest extends TestCase
{
    public static function cantidadesInvalidas(): array
    {
        return [['-1'], ['1.0001'], ['1,5'], ['1e3'], [''], ['10000000000']];
    }

    #[DataProvider('cantidadesInvalidas')]
    public function test_cantidades_invalidas_generan_error_de_materiales_sin_bd(string $valor): void
    {
        try {
            CantidadesEnvasado::milesimas($valor);
            $this->fail('Debió rechazar el formato de consumo.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('materiales', $error->errors());
        }
    }

    public static function paquetesInvalidos(): array
    {
        return [['{'], ['{"estado":"pendiente"}'], [false], [['estado' => 'pendiente']], [array_fill(0, 10001, [])], ['null']];
    }

    #[DataProvider('paquetesInvalidos')]
    public function test_estructura_de_paquetes_invalida_no_se_interpreta_como_composicion(mixed $valor): void
    {
        try {
            LeerAgrupaciones::leer($valor);
            $this->fail('Debió rechazar la estructura de composición.');
        } catch (InvalidArgumentException $error) {
            $this->assertNotSame('', $error->getMessage());
        }
    }
}
