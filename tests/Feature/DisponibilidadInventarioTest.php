<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisponibilidadInventarioTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(bool $permiso = true): User
    {
        $rol = Role::create(['nombre' => 'Producción/Reparto', 'es_sistema' => true]);
        if ($permiso) {
            $p = Permiso::create(['nombre' => 'inventario.ver', 'descripcion' => 'Ver inventario']);
            $rol->permisos()->attach($p);
        }
        return User::create([
            'first_name' => 'Juan', 'last_name' => 'Inventario',
            'email' => 'inv@example.com', 'password' => 'password123',
            'role_id' => $rol->id, 'status' => true,
        ]);
    }

    private function presentacion(): PresentacionProducto
    {
        $categoria = Categoria::create(['nombre' => 'Limpieza', 'estado' => true]);
        $producto = Producto::create([
            'categoria_id' => $categoria->id, 'codigo' => 'P-S3-01',
            'nombre' => 'Desinfectante', 'estado' => true,
        ]);
        return PresentacionProducto::create([
            'producto_id' => $producto->id, 'codigo_sku' => 'DES-1L',
            'contenido' => 1, 'unidad_medida' => 'L',
            'venta_por_unidad' => true, 'venta_por_empaque' => true,
            'unidades_por_empaque' => 12, 'estado' => true,
        ]);
    }

    public function test_stock_sin_movimientos_se_muestra_cero(): void
    {
        $usuario = $this->usuario();
        $this->presentacion();
        $this->actingAs($usuario)->get(route('inventario.index'))
            ->assertOk()->assertSee('DES-1L')->assertSee('0');
    }

    public function test_disponibilidad_agrupa_cajas_y_unidades_del_mismo_sku(): void
    {
        $usuario = $this->usuario();
        $presentacion = $this->presentacion();
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 25]);
        $cliente = Cliente::create(['nombre' => 'Bodega', 'estado' => true]);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id, 'usuario_id' => $usuario->id,
            'fecha_pedido' => '2026-10-13', 'fecha_entrega' => '2026-10-15',
            'condicion_pago' => 'contado', 'cliente_nombre' => 'Bodega',
            'estado' => 'confirmado', 'total' => 150,
        ]);
        $pedido->detalles()->createMany([
            ['presentacion_producto_id' => $presentacion->id, 'codigo_sku' => 'DES-1L',
             'descripcion_producto' => 'Desinfectante', 'modalidad' => 'empaque',
             'cantidad' => 2, 'precio_unitario' => 60, 'importe' => 120],
            ['presentacion_producto_id' => $presentacion->id, 'codigo_sku' => 'DES-1L',
             'descripcion_producto' => 'Desinfectante', 'modalidad' => 'unidad',
             'cantidad' => 2, 'precio_unitario' => 5, 'importe' => 10],
        ]);
        $this->actingAs($usuario)->get(route('inventario.index', ['pedido_id' => $pedido->id]))
            ->assertOk()->assertSee('Stock insuficiente'); // 26 > 25
    }

    public function test_sin_permiso_no_consulta_inventario(): void
    {
        $this->actingAs($this->usuario(false))
            ->get(route('inventario.index'))->assertForbidden();
    }

    public function test_pedido_cancelado_no_es_consultable(): void
    {
        $usuario = $this->usuario();
        $cliente = Cliente::create(['nombre' => 'Bodega', 'estado' => true]);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id, 'usuario_id' => $usuario->id,
            'fecha_pedido' => '2026-10-13', 'fecha_entrega' => '2026-10-15',
            'cliente_nombre' => 'Bodega', 'estado' => 'cancelado', 'total' => 0,
        ]);
        $this->actingAs($usuario)->get(route('inventario.index', ['pedido_id' => $pedido->id]))
            ->assertNotFound();
    }
}
