<?php

namespace Tests\Unit;

use App\Services\CalculadoraPedido;
use InvalidArgumentException;
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
}
