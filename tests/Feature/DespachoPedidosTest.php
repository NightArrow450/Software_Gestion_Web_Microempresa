<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DespachoPedidosTest extends TestCase
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


    public function test_despacho_registra_usuario_fecha_observacion_e_historial(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $p = $this->pedido($u, 'listo_despacho');
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p), [
            'observacion_despacho' => 'Repartidor recogió la nota',
        ])->assertRedirect(route('cumplimiento.show', $p));
        $this->assertDatabaseHas('pedidos', [
            'id'=>$p->id, 'estado'=>'despachado', 'despachado_por_id'=>$u->id,
            'observacion_despacho'=>'Repartidor recogió la nota',
        ]);
        $this->assertNotNull($p->fresh()->despachado_en);
        $this->assertDatabaseHas('pedido_estados_historial', [
            'pedido_id'=>$p->id, 'estado_anterior'=>'listo_despacho',
            'estado_nuevo'=>'despachado', 'usuario_id'=>$u->id,
        ]);
    }

    public function test_no_despacha_confirmado_ni_cancelado(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        foreach (['confirmado','cancelado','entregado'] as $estado) {
            $p = $this->pedido($u, $estado);
            $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
                ->assertSessionHasErrors('estado');
            $this->assertSame($estado, $p->fresh()->estado);
        }
        $this->assertDatabaseCount('pedido_estados_historial', 0);
    }

    public function test_sin_permiso_de_despachar_recibe_403(): void
    {
        $u = $this->usuario(['pedidos.ver']);
        $p = $this->pedido($u, 'listo_despacho');
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
            ->assertForbidden();
        $this->assertSame('listo_despacho', $p->fresh()->estado);
    }

    public function test_no_despacha_dos_veces(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.despachar']);
        $p = $this->pedido($u, 'listo_despacho');
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))->assertRedirect();
        $this->actingAs($u)->post(route('cumplimiento.despachar', $p))
            ->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('pedido_estados_historial', 1);
    }

}
