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
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProcesarPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(): array
    {
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00', 'America/Lima'));
        $rol = Role::create(['nombre' => 'Operaciones Comerciales', 'es_sistema' => true]);
        foreach (['pedidos.crear', 'pedidos.ver', 'pedidos.editar'] as $nombre) {
            $permiso = Permiso::create(['nombre' => $nombre, 'descripcion' => $nombre]);
            $rol->permisos()->attach($permiso->id);
        }
        $usuario = User::create([
            'first_name' => 'Ventas', 'last_name' => 'Prueba', 'email' => 'ventas@local.test',
            'password' => 'password123', 'role_id' => $rol->id, 'status' => true,
        ]);
        $cliente = Cliente::create(['nombre' => 'Mercado Prueba', 'estado' => true]);
        $categoria = Categoria::create(['codigo' => 'C900', 'nombre' => 'Limpieza', 'estado' => true]);
        $producto = Producto::create(['codigo' => 'P900', 'categoria_id' => $categoria->id, 'nombre' => 'Lejía', 'estado' => true]);
        $presentacion = PresentacionProducto::create([
            'producto_id' => $producto->id, 'codigo_sku' => 'P900-900ML', 'contenido' => 900,
            'unidad_medida' => 'ml', 'precio_unitario' => '4.50',
            'venta_por_unidad' => true, 'venta_por_empaque' => false, 'estado' => true,
        ]);
        $this->actingAs($usuario);
        $datos = [
            'cliente_id' => $cliente->id, 'fecha_pedido' => '2026-10-12',
            'fecha_entrega' => '2026-10-13', 'condicion_pago' => 'contado',
            'detalles' => [[
                'presentacion_producto_id' => $presentacion->id,
                'modalidad' => 'unidad', 'cantidad' => 2,
            ]],
        ];
        $this->post(route('pedidos.store'), $datos)->assertRedirect();

        return [$usuario, Pedido::firstOrFail(), $datos];
    }

    public function test_confirma_un_pedido_y_registra_responsable_fecha_e_historial(): void
    {
        [$usuario, $pedido] = $this->escenario();
        $this->post(route('pedidos.procesar', $pedido), ['accion' => 'confirmar'])->assertRedirect();
        $pedido->refresh();
        $this->assertSame('confirmado', $pedido->estado);
        $this->assertSame($usuario->id, $pedido->procesado_por_id);
        $this->assertNotNull($pedido->procesado_en);
        $this->assertDatabaseHas('pedido_estados_historial', [
            'pedido_id' => $pedido->id, 'estado_anterior' => 'registrado', 'estado_nuevo' => 'confirmado',
        ]);
        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_observar_pedido_exige_motivo_y_permita_corregirlo(): void
    {
        [, $pedido, $datos] = $this->escenario();
        $this->post(route('pedidos.procesar', $pedido), ['accion' => 'observar'])
            ->assertSessionHasErrors('motivo');
        $this->post(route('pedidos.procesar', $pedido), [
            'accion' => 'observar', 'motivo' => 'Revisar cantidades',
        ])->assertRedirect();
        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => 'observado']);
        $datos['detalles'][0]['cantidad'] = 3;
        $datos['detalles'][0]['id'] = $pedido->detalles()->sole()->id;
        $datos['_version'] = $pedido->fresh()->version;
        $datos['_updated_at'] = (string) $pedido->fresh()->getRawOriginal('updated_at');
        $datos['motivo'] = 'Se corrige la cantidad observada de dos a tres unidades.';
        $this->put(route('pedidos.update', $pedido), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'total' => '13.50']);
        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_no_permite_confirmar_dos_veces(): void
    {
        [, $pedido] = $this->escenario();
        $this->post(route('pedidos.procesar', $pedido), ['accion' => 'confirmar'])->assertRedirect();
        $this->post(route('pedidos.procesar', $pedido), ['accion' => 'confirmar'])
            ->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('pedido_estados_historial', 2);
    }
}
