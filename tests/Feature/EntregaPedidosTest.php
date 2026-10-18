<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntregaPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create(['nombre' => 'Producción/Reparto', 'es_sistema' => true]);
        foreach ($permisos as $nombre) {
            $permiso = Permiso::firstOrCreate(
                ['nombre' => $nombre], ['descripcion' => $nombre]
            );
            $rol->permisos()->attach($permiso->id);
        }
        return User::create([
            'first_name' => 'Operador', 'last_name' => 'Prueba',
            'email' => 'operador@local.test', 'password' => 'password123',
            'role_id' => $rol->id, 'status' => true,
        ]);
    }

    private function pedido(User $usuario, string $estado = 'confirmado'): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente prueba', 'estado' => true]);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id, 'usuario_id' => $usuario->id,
            'fecha_pedido' => '2026-10-17', 'fecha_entrega' => '2026-10-19',
            'condicion_pago' => 'contado', 'estado' => $estado,
            'total' => '21.00', 'cliente_nombre' => 'Cliente prueba',
        ]);
        $pedido->detalles()->create([
            'codigo_sku' => 'TEST-1L',
            'descripcion_producto' => 'Producto de prueba',
            'modalidad' => 'unidad', 'cantidad' => 2,
            'precio_unitario' => '10.50', 'importe' => '21.00',
        ]);
        return $pedido;
    }


    public function test_entrega_registra_fecha_responsable_y_observacion(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.entregar']);
        $p = $this->pedido($u, 'despachado');
        $this->actingAs($u)->post(route('cumplimiento.entregar', $p), [
            'observacion_entrega'=>'Recibido por el cliente',
        ])->assertRedirect(route('cumplimiento.show', $p));
        $this->assertDatabaseHas('pedidos', [
            'id'=>$p->id, 'estado'=>'entregado', 'entregado_por_id'=>$u->id,
            'observacion_entrega'=>'Recibido por el cliente',
        ]);
        $this->assertNotNull($p->fresh()->entregado_en);
        $this->assertDatabaseHas('pedido_estados_historial', [
            'pedido_id'=>$p->id, 'estado_anterior'=>'despachado',
            'estado_nuevo'=>'entregado', 'usuario_id'=>$u->id,
        ]);
    }

    public function test_no_entrega_pedido_sin_despacho(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.entregar']);
        foreach (['confirmado','cancelado','listo_despacho'] as $estado) {
            $p = $this->pedido($u, $estado);
            $this->actingAs($u)->post(route('cumplimiento.entregar', $p))
                ->assertSessionHasErrors('estado');
            $this->assertSame($estado, $p->fresh()->estado);
        }
        $this->assertDatabaseCount('pedido_estados_historial', 0);
    }

    public function test_no_permite_duplicar_la_entrega(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.entregar']);
        $p = $this->pedido($u, 'despachado');
        $this->actingAs($u)->post(route('cumplimiento.entregar', $p))->assertRedirect();
        $this->actingAs($u)->post(route('cumplimiento.entregar', $p))
            ->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('pedido_estados_historial', 1);
    }

    public function test_sin_permiso_de_entregar_recibe_403(): void
    {
        $u = $this->usuario(['pedidos.ver']);
        $p = $this->pedido($u, 'despachado');
        $this->actingAs($u)->post(route('cumplimiento.entregar', $p))
            ->assertForbidden();
        $this->assertSame('despachado', $p->fresh()->estado);
    }

}
