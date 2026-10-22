<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegridadHistoricaTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_se_puede_borrar_producto_con_presentaciones(): void
    {
        $categoria = Categoria::create(['nombre' => 'Prueba', 'estado' => true]);
        $producto = Producto::create(['codigo' => 'HIST', 'nombre' => 'Histórico', 'categoria_id' => $categoria->id, 'estado' => true]);
        PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => 'HIST-1', 'contenido' => 1, 'unidad_medida' => 'L', 'estado' => true]);
        $this->expectException(QueryException::class);
        $producto->delete();
    }

    public function test_todas_las_referencias_historicas_se_conservan(): void
    {
        foreach (['pedido_detalles', 'pedido_estados_historial', 'pedidos', 'variantes_producto', 'presentaciones_producto'] as $tabla) {
            foreach (DB::getSchemaBuilder()->getForeignKeys($tabla) as $referencia) {
                $this->assertContains(strtolower($referencia['on_delete']), ['restrict', 'no action']);
            }
        }
    }
}
