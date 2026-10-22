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

class DespachoPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create([
            'nombre' => 'Producción/Reparto',
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
            'first_name' => 'Operador', 'last_name' => 'Prueba',
            'email' => 'operador@local.test', 'password' => 'password123',
            'role_id' => $rol->id, 'status' => true,
        ]);
    }

    private function presentacion(string $sku = 'TEST-1L', int $porEmpaque = 6): PresentacionProducto
    {
        $categoria = Categoria::firstOrCreate(['nombre' => 'Limpieza'], ['estado' => true]);
        $producto = Producto::create([
            'codigo' => 'COD-'.$sku,
            'categoria_id' => $categoria->id,
            'nombre' => 'Producto '.$sku,
            'estado' => true,
        ]);

        return PresentacionProducto::create([
            'producto_id' => $producto->id,
            'codigo_sku' => $sku,
            'contenido' => 1,
            'unidad_medida' => 'L',
            'venta_por_unidad' => true,
            'venta_por_empaque' => true,
            'unidades_por_empaque' => $porEmpaque,
            'estado' => true,
        ]);
    }

    private function pedido(
        User $usuario,
        string $estado = 'listo_despacho',
        int $existencias = 10
    ): Pedido {
        $cliente = Cliente::create(['nombre' => 'Cliente prueba', 'estado' => true]);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'fecha_pedido' => '2026-10-17',
            'fecha_entrega' => '2026-10-19',
            'condicion_pago' => 'contado',
            'estado' => $estado,
            'total' => '21.00',
            'cliente_nombre' => 'Cliente prueba',
        ]);
        $presentacion = $this->presentacion('TEST-1L-'.$pedido->id);
        $pedido->detalles()->create([
            'presentacion_producto_id' => $presentacion->id,
            'codigo_sku' => $presentacion->codigo_sku,
            'descripcion_producto' => 'Producto de prueba',
            'modalidad' => 'unidad',
            'cantidad' => 2,
            'precio_unitario' => '10.50',
            'importe' => '21.00',
        ]);
        Inventario::create([
            'presentacion_producto_id' => $presentacion->id,
            'cantidad_actual' => $existencias,
        ]);

        return $pedido;
    }

    public function test_despacho_descuenta_y_registra_historial_usuario_y_pedido(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $p = $this->pedido($u);
        $detalle = $p->detalles()->firstOrFail();

        $this->actingAs($u)->post(route('cumplimiento.despachar', $p), [
            'observacion_despacho' => 'Repartidor recogió la nota',
        ])->assertRedirect(route('cumplimiento.show', $p));

        $this->assertDatabaseHas('pedidos', [
            'id' => $p->id,
            'estado' => 'despachado',
            'despachado_por_id' => $u->id,
            'observacion_despacho' => 'Repartidor recogió la nota',
        ]);
        $this->assertNotNull($p->fresh()->despachado_en);
        $this->assertDatabaseHas('inventarios', [
            'presentacion_producto_id' => $detalle->presentacion_producto_id,
            'cantidad_actual' => 8,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'pedido_id' => $p->id,
            'presentacion_producto_id' => $detalle->presentacion_producto_id,
            'usuario_id' => $u->id,
            'tipo' => 'salida',
            'stock_anterior' => 10,
            'stock_nuevo' => 8,
        ]);
        $this->assertDatabaseHas('pedido_estados_historial', [
            'pedido_id' => $p->id,
            'estado_anterior' => 'listo_despacho',
            'estado_nuevo' => 'despachado',
            'usuario_id' => $u->id,
        ]);
    }

    public function test_no_despacha_confirmado_ni_cancelado(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        foreach (['confirmado', 'cancelado', 'entregado'] as $estado) {
            $p = $this->pedido($u, $estado);
            $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
                ->assertSessionHasErrors('estado');
            $this->assertSame($estado, $p->fresh()->estado);
        }
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_estados_historial', 0);
    }

    public function test_sin_permiso_no_descuenta_ni_despacha(): void
    {
        $u = $this->usuario(['pedidos.ver']);
        $p = $this->pedido($u);
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
            ->assertForbidden();
        $this->assertSame('listo_despacho', $p->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_no_despacha_dos_veces_ni_descontar_stock_doble(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $p = $this->pedido($u);
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))->assertRedirect();
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
            ->assertSessionHasErrors('estado');

        $this->assertSame(8, (int) Inventario::firstOrFail()->cantidad_actual);
        $this->assertDatabaseCount('pedido_estados_historial', 1);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_stock_insuficiente_cancela_toda_la_transaccion(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $p = $this->pedido($u, 'listo_despacho', 1);
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
            ->assertSessionHasErrors('inventario');

        $this->assertSame('listo_despacho', $p->fresh()->estado);
        $this->assertSame(1, (int) Inventario::firstOrFail()->cantidad_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_estados_historial', 0);
    }

    public function test_empaques_descuentan_unidades_y_no_cajas(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $p = $this->pedido($u, 'listo_despacho', 20);
        $p->detalles()->firstOrFail()->update([
            'modalidad' => 'empaque',
            'cantidad' => 2,
        ]);
        // 2 empaques de 6 unidades = 12 unidades de stock.
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
            ->assertRedirect();
        $this->assertSame(8, (int) Inventario::firstOrFail()->cantidad_actual);
        $this->assertDatabaseHas('movimientos_inventario', [
            'pedido_id' => $p->id,
            'cantidad' => 12,
            'stock_anterior' => 20,
            'stock_nuevo' => 8,
        ]);
    }

    public function test_si_un_sku_no_alcanza_no_descuenta_los_otros(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $p = $this->pedido($u, 'listo_despacho', 10);
        $otra = $this->presentacion('OTRO-1L');
        Inventario::create([
            'presentacion_producto_id' => $otra->id,
            'cantidad_actual' => 0,
        ]);
        $p->detalles()->create([
            'presentacion_producto_id' => $otra->id,
            'codigo_sku' => $otra->codigo_sku,
            'descripcion_producto' => 'Segundo producto',
            'modalidad' => 'unidad',
            'cantidad' => 1,
            'precio_unitario' => '4.00',
            'importe' => '4.00',
        ]);

        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
            ->assertSessionHasErrors('inventario');
        $this->assertSame('listo_despacho', $p->fresh()->estado);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 10]);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_no_despacha_pedidos_sin_sku_validamente_vinculado(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $p = $this->pedido($u);
        $p->detalles()->firstOrFail()->update(['presentacion_producto_id' => null]);
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
            ->assertSessionHasErrors('inventario');
        $this->assertSame('listo_despacho', $p->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_informa_todos_los_sku_faltantes_sin_modificar_datos(): void
    {
        $usuario = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $pedido = $this->pedido($usuario, 'listo_despacho', 1);
        $primero = $pedido->detalles()->firstOrFail();
        $otra = $this->presentacion('FALTANTE-2');
        $pedido->detalles()->create(array_merge($primero->only([
            'descripcion_producto', 'modalidad', 'cantidad', 'precio_unitario', 'importe',
        ]), ['presentacion_producto_id' => $otra->id, 'codigo_sku' => $otra->codigo_sku]));

        $this->actingAs($usuario)->post(route('cumplimiento.despachar', $pedido))
            ->assertSessionHasErrors(['inventario']);
        $mensajes = session('errors')->get('inventario');
        $this->assertCount(2, $mensajes);
        $this->assertStringContainsString($primero->codigo_sku, $mensajes[0]);
        $this->assertStringContainsString('necesitas 2 unidad(es) y hay 1', $mensajes[0]);
        $this->assertStringContainsString('FALTANTE-2', $mensajes[1]);
        $this->assertSame(1, (int) Inventario::firstOrFail()->cantidad_actual);
        $this->assertSame('listo_despacho', $pedido->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_estados_historial', 0);
    }
}
