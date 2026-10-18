<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovimientosInventarioTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create([
            'nombre' => 'Operaciones Comerciales',
            'es_sistema' => true,
        ]);

        foreach ($permisos as $nombre) {
            $permiso = Permiso::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $nombre]
            );

            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'María',
            'last_name' => 'Almacén',
            'email' => 'almacen@example.com',
            'password' => 'password123',
            'role_id' => $rol->id,
            'status' => true,
        ]);
    }

    private function presentacion(): PresentacionProducto
    {
        $categoria = Categoria::create([
            'nombre' => 'Limpieza',
            'estado' => true,
        ]);

        $producto = Producto::create([
            'codigo' => 'INV-01',
            'categoria_id' => $categoria->id,
            'nombre' => 'Lejía',
            'estado' => true,
        ]);

        return PresentacionProducto::create([
            'producto_id' => $producto->id,
            'codigo_sku' => 'LEJ-1L',
            'contenido' => 1,
            'unidad_medida' => 'L',
            'venta_por_unidad' => true,
            'venta_por_empaque' => true,
            'unidades_por_empaque' => 12,
            'estado' => true,
        ]);
    }

    private function datos(
        PresentacionProducto $p,
        string $tipo,
        int $cantidad
    ): array {
        return [
            'presentacion_producto_id' => $p->id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'motivo' => 'Movimiento de inventario probado',
        ];
    }

    public function test_entrada_y_salida_actualizan_stock_con_historial(): void
    {
        $u = $this->usuario([
            'inventario.ver',
            'inventario.entrada',
            'inventario.salida',
        ]);

        $p = $this->presentacion();

        $this->actingAs($u)
            ->post(
                route('inventario.movimientos.store'),
                $this->datos($p, 'entrada', 20)
            )
            ->assertRedirect();

        $this->actingAs($u)
            ->post(
                route('inventario.movimientos.store'),
                $this->datos($p, 'salida', 5)
            )
            ->assertRedirect();

        $this->assertDatabaseHas('inventarios', [
            'presentacion_producto_id' => $p->id,
            'cantidad_actual' => 15,
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'tipo' => 'entrada',
            'stock_anterior' => 0,
            'stock_nuevo' => 20,
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'tipo' => 'salida',
            'stock_anterior' => 20,
            'stock_nuevo' => 15,
        ]);
    }

    public function test_rechaza_salida_superior_al_stock(): void
    {
        // CORREGIDO: se requiere permiso de consulta y salida.
        $u = $this->usuario([
            'inventario.ver',
            'inventario.salida',
        ]);

        $p = $this->presentacion();

        Inventario::create([
            'presentacion_producto_id' => $p->id,
            'cantidad_actual' => 3,
        ]);

        $this->actingAs($u)
            ->post(
                route('inventario.movimientos.store'),
                $this->datos($p, 'salida', 10)
            )
            ->assertSessionHasErrors('cantidad');

        $this->assertDatabaseHas('inventarios', [
            'presentacion_producto_id' => $p->id,
            'cantidad_actual' => 3,
        ]);

        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_ajuste_permita_cero_y_registra_responsable(): void
    {
        // CORREGIDO: se requiere permiso de consulta y ajuste.
        $u = $this->usuario([
            'inventario.ver',
            'inventario.ajustar',
        ]);

        $p = $this->presentacion();

        Inventario::create([
            'presentacion_producto_id' => $p->id,
            'cantidad_actual' => 11,
        ]);

        $this->actingAs($u)
            ->post(
                route('inventario.movimientos.store'),
                $this->datos($p, 'ajuste', 0)
            )
            ->assertRedirect();

        $this->assertDatabaseHas('inventarios', [
            'presentacion_producto_id' => $p->id,
            'cantidad_actual' => 0,
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'tipo' => 'ajuste',
            'usuario_id' => $u->id,
            'cantidad' => 11,
        ]);
    }

    public function test_usuario_sin_permiso_es_rechazado_en_servidor(): void
    {
        $u = $this->usuario([
            'inventario.ver',
        ]);

        $p = $this->presentacion();

        $this->actingAs($u)
            ->post(
                route('inventario.movimientos.store'),
                $this->datos($p, 'entrada', 5)
            )
            ->assertForbidden();

        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
