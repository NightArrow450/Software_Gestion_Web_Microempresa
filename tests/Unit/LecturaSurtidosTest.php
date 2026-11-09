<?php

namespace Tests\Unit;

use App\Services\LeerAgrupaciones;
use PHPUnit\Framework\TestCase;

class LecturaSurtidosTest extends TestCase
{
    public function test_lee_listas_y_json_sin_inventar_paquetes_o_distribuciones(): void
    {
        $lector = new LeerAgrupaciones;
        $paquetes = [['capacidad' => 12, 'componentes' => [['sku_id' => 17, 'cantidad' => 5]]], ['capacidad' => 30, 'componentes' => [['sku_id' => 17, 'cantidad' => 12]]]];
        $this->assertSame([], $lector->leer(null));
        $this->assertSame([], $lector->leer(''));
        $this->assertSame($paquetes, $lector->leer($paquetes));
        $this->assertSame($paquetes, $lector->leer(json_encode($paquetes, JSON_THROW_ON_ERROR)));
    }
}
