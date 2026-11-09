<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlStockTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create(['nombre' => 'Operaciones Comerciales', 'es_sistema' => true]);
        foreach ($permisos as $nombre) {
            $p = Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre]);
            $rol->permisos()->attach($p->id);
        }

        return User::create([
            'first_name' => 'Luisa', 'last_name' => 'Prueba', 'email' => 'stock@example.com',
            'password' => 'password123', 'role_id' => $rol->id, 'status' => true,
        ]);
    }

    private function presentacion(): PresentacionProducto
    {
        $categoria = Categoria::create(['nombre' => 'Productos', 'estado' => true]);
        $producto = Producto::create(['codigo' => 'ST-001', 'categoria_id' => $categoria->id,
            'nombre' => 'Jabón', 'estado' => true]);

        return PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => 'JABON-1',
            'contenido' => 1, 'unidad_medida' => 'L', 'venta_por_unidad' => true, 'estado' => true]);
    }

    public function test_muestra_alerta_agotado_sin_movimientos(): void
    {
        $usuario = $this->usuario(['inventario.ver']);
        $this->presentacion();
        $this->actingAs($usuario)->get(route('inventario.stock'))
            ->assertOk()->assertSee('JABON-1')->assertSee('Agotado');
    }

    public function test_actualiza_minimo_sin_cambiar_existencia(): void
    {
        $usuario = $this->usuario(['inventario.ver', 'inventario.ajustar']);
        $p = $this->presentacion();
        Inventario::create(['presentacion_producto_id' => $p->id, 'cantidad_actual' => 7]);
        $this->actingAs($usuario)->patch(route('inventario.stock.minimo', $p), ['stock_minimo' => 15])
            ->assertRedirect();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $p->id,
            'cantidad_actual' => 7, 'stock_minimo' => 15]);
        $this->actingAs($usuario)->get(route('inventario.stock', ['filtro' => 'bajo']))
            ->assertOk()->assertSee('JABON-1');
    }

    public function test_no_autorizado_no_cambia_stock_minimo(): void
    {
        $usuario = $this->usuario(['inventario.ver']);
        $p = $this->presentacion();
        $this->actingAs($usuario)->patch(route('inventario.stock.minimo', $p), ['stock_minimo' => 30])
            ->assertForbidden();
        $this->assertDatabaseCount('inventarios', 0);
    }

    public function test_stock_normal_no_aparece_como_bajo(): void
    {
        $usuario = $this->usuario(['inventario.ver']);
        $p = $this->presentacion();
        Inventario::create(['presentacion_producto_id' => $p->id, 'cantidad_actual' => 25]);
        $this->actingAs($usuario)->get(route('inventario.stock', ['filtro' => 'bajo']))
            ->assertOk()->assertDontSee('JABON-1');
        $this->actingAs($usuario)->get(route('inventario.stock', ['filtro' => 'normal']))
            ->assertOk()->assertSee('JABON-1');
    }

    public function test_resumen_global_conserva_limites_y_no_se_recorta_por_busqueda_o_pagina(): void
    {
        config(['envasado.control_materiales_exacto' => true]);
        $usuario = $this->usuario(['inventario.ver']);
        $this->actingAs($usuario)->get(route('inventario.stock'))->assertOk()->assertViewHas('resumen', ['agotado' => 0, 'bajo' => 0, 'normal' => 0]);
        $p = $this->presentacion();
        foreach ([0, 10, 11] as $cantidad) {
            $otra = $p->replicate();
            $otra->codigo_sku = 'STOCK-'.$cantidad;
            $otra->save();
            Inventario::create(['presentacion_producto_id' => $otra->id, 'cantidad_actual' => $cantidad, 'stock_minimo' => 10]);
        }
        foreach ([0, 0.5, 1, 1.001] as $cantidad) {
            Insumo::create(['codigo' => 'INS-'.$cantidad, 'nombre' => 'Material '.$cantidad, 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => $cantidad, 'stock_minimo' => 1, 'estado' => true]);
        }
        Insumo::create(['codigo' => 'INACTIVO', 'nombre' => 'Material inactivo', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 0, 'estado' => false]);
        $respuesta = $this->get(route('inventario.stock', ['buscar' => 'STOCK-11', 'filtro' => 'normal', 'productos_pagina' => 1]));
        $respuesta->assertOk()->assertViewHas('resumen', ['agotado' => 3, 'bajo' => 3, 'normal' => 2]);
        $this->assertSame(['STOCK-11'], $respuesta->viewData('presentaciones')->pluck('codigo_sku')->all());
        $this->assertSame(0, $respuesta->viewData('insumos')->total());
    }
}
