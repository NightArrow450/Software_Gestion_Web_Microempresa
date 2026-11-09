<?php

namespace Tests\Unit;

use App\Services\ControlEntregaPedido;
use PHPUnit\Framework\TestCase;

class IdentidadFisicaTest extends TestCase
{
    public function test_linea_normal_conserva_identificador_entero(): void
    {
        $this->assertSame(12, ControlEntregaPedido::claveFisica(12));
        $this->assertSame(12, ControlEntregaPedido::claveFisica(12, null));
    }

    public function test_componente_no_colisiona_con_linea_normal_ni_otro_componente(): void
    {
        $this->assertSame('surtido:12', ControlEntregaPedido::claveFisica(12, 12));
        $this->assertSame('surtido:15', ControlEntregaPedido::claveFisica(12, 15));
        $this->assertNotSame(ControlEntregaPedido::claveFisica(12), ControlEntregaPedido::claveFisica(12, 12));
    }
}
