<?php

namespace Tests\Unit;

use App\Services\GestionarSurtidos;
use PHPUnit\Framework\TestCase;

class LecturaSurtidosTest extends TestCase
{
    public function test_lee_listas_y_json_sin_inventar_paquetes_o_distribuciones(): void
    {
        $lector = new GestionarSurtidos;
        $paquetes = [['estado' => 'pendiente', 'componentes' => []], ['estado' => 'definida', 'componentes' => [['presentacion_producto_id' => 17, 'cantidad' => 12]]]];
        $this->assertSame([], $lector->leerPaquetes(null));
        $this->assertSame([], $lector->leerPaquetes(''));
        $this->assertSame($paquetes, $lector->leerPaquetes($paquetes));
        $this->assertSame($paquetes, $lector->leerPaquetes(json_encode($paquetes, JSON_THROW_ON_ERROR)));
    }
}
