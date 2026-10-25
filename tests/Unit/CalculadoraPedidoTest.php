<?php

namespace Tests\Unit;

use App\Services\CalculadoraPedido;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculadoraPedidoTest extends TestCase
{
    public function test_multiplica_cantidad_y_precio_sin_perder_centimos(): void
    {
        $this->assertSame('54.00', (new CalculadoraPedido)->calcularImporte(12, '4.50'));
        $this->assertSame('0.30', (new CalculadoraPedido)->calcularImporte(3, '0.10'));
    }

    public function test_no_acepta_cantidades_invalidas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CalculadoraPedido)->calcularImporte(0, '4.50');
    }

    public static function preciosInvalidos(): array
    {
        return array_map(fn (string $precio): array => [$precio], ['-1', '1.001', '1,00', '1e2', ' 1.00', '1.00 ', '', 'abc']);
    }

    #[DataProvider('preciosInvalidos')]
    public function test_rechaza_formatos_monetarios_que_no_son_centimos(string $precio): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CalculadoraPedido)->calcularImporte(1, $precio);
    }

    public function test_calcula_importes_grandes_sin_imponer_el_tope_del_controlador(): void
    {
        $this->assertSame('999900.00', (new CalculadoraPedido)->calcularImporte(10000, '99.99'));
        $this->assertSame('1000000.00', (new CalculadoraPedido)->calcularImporte(10000, '100.00'));
    }

    public function test_admite_float_y_cero_en_el_calculo_sin_cambiar_la_validacion_comercial(): void
    {
        $this->assertSame('0.21', (new CalculadoraPedido)->calcularImporte(3, 0.07));
        $this->assertSame('0.00', (new CalculadoraPedido)->calcularImporte(1, '0'));
    }
}
