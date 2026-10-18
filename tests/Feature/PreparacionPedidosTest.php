<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreparacionPedidosTest extends TestCase
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


    public function test_produccion_ve_pedidos_confirmados(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.preparar']);
        $p = $this->pedido($u);
        $this->actingAs($u)->get(route('cumplimiento.index'))
            ->assertOk()->assertSee($p->numero);
    }

    public function test_inicia_y_finaliza_preparacion_registrando_responsable_e_historial(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.preparar']);
        $p = $this->pedido($u);
        $this->actingAs($u)->post(route('cumplimiento.iniciar-preparacion', $p))
            ->assertRedirect(route('cumplimiento.show', $p));
        $this->assertDatabaseHas('pedidos', [
            'id'=>$p->id, 'estado'=>'en_preparacion', 'preparacion_iniciada_por_id'=>$u->id,
        ]);
        $this->assertNotNull($p->fresh()->preparacion_iniciada_en);
        $this->actingAs($u)->post(route('cumplimiento.finalizar-preparacion', $p))
            ->assertRedirect(route('cumplimiento.show', $p));
        $this->assertDatabaseHas('pedidos', [
            'id'=>$p->id, 'estado'=>'listo_despacho', 'preparacion_finalizada_por_id'=>$u->id,
        ]);
        $this->assertNotNull($p->fresh()->preparacion_finalizada_en);
        $this->assertDatabaseHas('pedido_estados_historial', [
            'pedido_id'=>$p->id, 'estado_anterior'=>'en_preparacion',
            'estado_nuevo'=>'listo_despacho', 'usuario_id'=>$u->id,
        ]);
    }

    public function test_no_inicia_pedido_cancelado_ni_salta_pasos(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.preparar']);
        $cancelado = $this->pedido($u, 'cancelado');
        $this->actingAs($u)->post(route('cumplimiento.iniciar-preparacion', $cancelado))
            ->assertSessionHasErrors('estado');
        $confirmado = $this->pedido($u, 'confirmado');
        $this->actingAs($u)->post(route('cumplimiento.finalizar-preparacion', $confirmado))
            ->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('pedido_estados_historial', 0);
    }

    public function test_no_puede_iniciar_dos_veces(): void
    {
        $u = $this->usuario(['pedidos.ver', 'cumplimiento.preparar']);
        $p = $this->pedido($u);
        $this->actingAs($u)->post(route('cumplimiento.iniciar-preparacion', $p))->assertRedirect();
        $this->actingAs($u)->post(route('cumplimiento.iniciar-preparacion', $p))
            ->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('pedido_estados_historial', 1);
    }

    public function test_sin_permiso_de_preparar_recibe_403(): void
    {
        $u = $this->usuario(['pedidos.ver']);
        $p = $this->pedido($u);
        $this->actingAs($u)->post(route('cumplimiento.iniciar-preparacion', $p))
            ->assertForbidden();
        $this->assertSame('confirmado', $p->fresh()->estado);
    }

}
