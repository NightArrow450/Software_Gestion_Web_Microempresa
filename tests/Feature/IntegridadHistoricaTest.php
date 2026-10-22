<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\MySql\EntornoMySql;
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

    public function test_mysql_de_pruebas_rechaza_nombre_de_base_real_antes_de_conectar(): void
    {
        $anterior = getenv('MYSQL_TEST_ENABLE');
        putenv('MYSQL_TEST_ENABLE=1');
        config(['database.connections.mysql_concurrencia.database' => 'software_gestion_web_microempresa', 'database.connections.mysql_concurrencia.username' => 'prueba']);
        try {
            $this->expectException(\RuntimeException::class);
            EntornoMySql::proteger();
        } finally {
            putenv($anterior === false ? 'MYSQL_TEST_ENABLE' : 'MYSQL_TEST_ENABLE='.$anterior);
        }
    }
}
