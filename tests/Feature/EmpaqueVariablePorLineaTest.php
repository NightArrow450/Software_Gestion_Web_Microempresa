<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmpaqueVariablePorLineaTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(?array $permisos = null): array
    {
        $this->freezeTime();
        $rol = Role::create(['nombre' => 'Operaciones Comerciales']);
        foreach ($permisos ?? ['pedidos.crear', 'pedidos.editar', 'pedidos.ver', 'inventario.ver', 'cumplimiento.despachar'] as $nombre) {
            $rol->permisos()->attach(Permiso::firstOrCreate(['nombre' => $nombre])->id);
        }
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'empaques@example.com', 'password' => 'Prueba12345', 'role_id' => $rol->id, 'status' => true]);
        $this->actingAs($usuario);
        $cliente = Cliente::create(['nombre' => 'Cliente Prueba', 'estado' => true]);
        $categoria = Categoria::create(['nombre' => 'Limpieza', 'estado' => true]);
        $producto = Producto::create(['codigo' => 'PRO-1', 'nombre' => 'Limpiador', 'categoria_id' => $categoria->id, 'estado' => true]);
        $presentacion = PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => 'PRO-1-L', 'contenido' => 1, 'unidad_medida' => 'L', 'tipo_empaque' => 'Caja', 'unidades_por_empaque' => 12, 'venta_por_unidad' => true, 'venta_por_empaque' => true, 'precio_unitario' => '2.50', 'estado' => true]);
        $datos = ['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $presentacion->id, 'modalidad' => 'empaque', 'cantidad' => 5, 'unidades_por_empaque' => 15]]];

        return [$datos, $presentacion, $usuario];
    }

    public function test_migracion_copia_tamano_sin_alterar_los_datos_existentes(): void
    {
        [$datos, $presentacion, $usuario] = $this->escenario();
        $pedido = Pedido::create(['cliente_id' => $datos['cliente_id'], 'usuario_id' => $usuario->id, 'fecha_pedido' => today(), 'fecha_entrega' => today(), 'condicion_pago' => 'contado', 'estado' => 'registrado', 'total' => '199.99', 'cliente_nombre' => 'Cliente Prueba']);
        $base = ['codigo_sku' => $presentacion->codigo_sku, 'descripcion_producto' => 'Descripción histórica', 'cantidad' => 5, 'precio_unitario' => '39.99', 'importe' => '199.95'];
        foreach (['empaque', 'unidad'] as $modalidad) {
            $pedido->detalles()->create($base + ['presentacion_producto_id' => $presentacion->id, 'modalidad' => $modalidad]);
        }
        $pedido->detalles()->create($base + ['modalidad' => 'empaque']);
        $migracion = require database_path('migrations/2026_10_22_023227_add_unidades_por_empaque_to_pedido_detalles_table.php');
        $migracion->down();
        $antes = DB::table('pedido_detalles')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
        $migracion->up();
        $despues = DB::table('pedido_detalles')->orderBy('id')->get();

        $this->assertSame([12, null, null], $despues->pluck('unidades_por_empaque')->all());
        $this->assertSame($antes, $despues->map(function ($fila): array {
            $datos = (array) $fila;
            unset($datos['unidades_por_empaque']);

            return $datos;
        })->all());
        $this->assertSame('199.99', $pedido->fresh()->total);
    }
}
