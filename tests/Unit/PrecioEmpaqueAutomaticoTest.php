<?php

namespace Tests\Unit;

use App\Models\PresentacionProducto;
use App\Http\Controllers\ProductoController;
use PHPUnit\Framework\TestCase;

class PrecioEmpaqueAutomaticoTest extends TestCase
{
    public function test_multiplica_el_precio_unitario_por_las_unidades(): void
    {
        $this->assertSame('54.00', PresentacionProducto::calcularPrecioEmpaque('4.50', 12));
        $this->assertSame('4.50', PresentacionProducto::calcularPrecioEmpaque('1.50', 3));
    }

    public function test_no_genera_precio_si_faltan_los_datos(): void
    {
        $this->assertNull(PresentacionProducto::calcularPrecioEmpaque(null, 12));
        $this->assertNull(PresentacionProducto::calcularPrecioEmpaque('4.50', null));
    }

    public function test_el_servidor_calcula_el_precio_y_no_confia_en_el_precio_enviado(): void
    {
        $controlador = new ProductoController();
        $metodo = new \ReflectionMethod(ProductoController::class, 'datosPresentacion');
        $datos = $metodo->invoke($controlador, [
            'venta_por_unidad' => false,
            'venta_por_empaque' => true,
            'precio_unitario' => '4.50',
            'precio_empaque' => '1.00', // El navegador no controla el precio final.
            'unidades_por_empaque' => 12,
            'tipo_empaque' => 'Caja',
            'contenido' => '900',
            'unidad_medida' => 'ml',
            'estado' => true,
        ], null, 'PRUEBA-900', true);

        $this->assertSame('54.00', $datos['precio_empaque']);
        $this->assertSame('4.50', $datos['precio_unitario']);
        $this->assertFalse($datos['venta_por_unidad']);
    }

    public function test_recalcula_para_cajas_con_precio_guardado_anteriormente(): void
    {
        $presentacion = new PresentacionProducto([
            'venta_por_empaque' => true,
            'precio_unitario' => '2.75',
            'unidades_por_empaque' => 6,
            'precio_empaque' => '10.00',
        ]);

        $this->assertSame('16.50', $presentacion->precio_empaque);
    }
}
