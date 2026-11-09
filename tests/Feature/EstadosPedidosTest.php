<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadosPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(
        string $nombreRol,
        array $nombresPermiso
    ): User {
        $rol = Role::create([
            'nombre' => $nombreRol,
            'es_sistema' => true,
        ]);

        foreach ($nombresPermiso as $nombre) {
            $permiso = Permiso::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $nombre]
            );

            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'Usuario',
            'last_name' => 'Prueba',
            'email' => strtolower(
                str_replace([' ', '/'], '_', $nombreRol)
            ).'@local.test',
            'password' => 'password123',
            'role_id' => $rol->id,
            'status' => true,
        ]);
    }

    private function crearPedido(
        User $vendedor,
        string $estado = 'registrado'
    ): Pedido {
        $cliente = Cliente::create([
            'nombre' => 'Cliente de prueba',
            'estado' => true,
        ]);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $vendedor->id,
            'fecha_pedido' => '2026-10-12',
            'fecha_entrega' => '2026-10-13',
            'condicion_pago' => 'contado',
            'estado' => $estado,
            'cliente_nombre' => $cliente->nombre,
            'total' => '35.00',
        ]);
    }

    public function test_administrador_puede_cancelar_pedido_registrado(): void
    {
        $admin = $this->crearUsuario(
            'Administrador',
            ['pedidos.ver', 'pedidos.cancelar']
        );

        $pedido = $this->crearPedido($admin);

        $this->actingAs($admin)
            ->post(route('pedidos.cancelar', $pedido), [
                'motivo' => 'Cliente canceló su pedido',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->id,
            'estado' => 'cancelado',
        ]);

        $this->assertDatabaseHas(
            'pedido_estados_historial',
            [
                'pedido_id' => $pedido->id,
                'usuario_id' => $admin->id,
                'estado_anterior' => 'registrado',
                'estado_nuevo' => 'cancelado',
                'motivo' => 'Cliente canceló su pedido',
            ]
        );
    }

    public function test_operaciones_puede_cancelar_pedido_confirmado(): void
    {
        $operador = $this->crearUsuario(
            'Operaciones Comerciales',
            ['pedidos.ver', 'pedidos.cancelar']
        );

        $pedido = $this->crearPedido(
            $operador,
            'confirmado'
        );

        $this->actingAs($operador)
            ->post(route('pedidos.cancelar', $pedido), [
                'motivo' => 'Cambio solicitado por cliente',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->id,
            'estado' => 'cancelado',
        ]);

        $this->assertDatabaseHas(
            'pedido_estados_historial',
            [
                'pedido_id' => $pedido->id,
                'usuario_id' => $operador->id,
                'estado_anterior' => 'confirmado',
                'estado_nuevo' => 'cancelado',
                'motivo' => 'Cambio solicitado por cliente',
            ]
        );
    }

    public function test_produccion_reparto_no_puede_cancelar(): void
    {
        $produccion = $this->crearUsuario(
            'Producción/Reparto',
            ['pedidos.ver']
        );

        $pedido = $this->crearPedido($produccion);

        $this->actingAs($produccion)
            ->post(route('pedidos.cancelar', $pedido), [
                'motivo' => 'Intento sin permiso',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->id,
            'estado' => 'registrado',
        ]);
    }

    public function test_cancelar_exige_motivo_y_no_permite_dos_cancelaciones(): void
    {
        $operador = $this->crearUsuario(
            'Operaciones Comerciales',
            ['pedidos.ver', 'pedidos.cancelar']
        );

        $pedido = $this->crearPedido($operador);

        // No se permite cancelar sin motivo.
        $this->actingAs($operador)
            ->post(route('pedidos.cancelar', $pedido), [])
            ->assertSessionHasErrors('motivo');

        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->id,
            'estado' => 'registrado',
        ]);

        // Primera cancelación válida.
        $this->actingAs($operador)
            ->post(route('pedidos.cancelar', $pedido), [
                'motivo' => 'Pedido anulado por solicitud',
            ])
            ->assertRedirect();

        // Segundo intento: debe rechazarse.
        $this->actingAs($operador)
            ->post(route('pedidos.cancelar', $pedido), [
                'motivo' => 'Segundo intento',
            ])
            ->assertSessionHasErrors('estado');

        // El pedido se conserva y existe
        // un único evento de cancelación.
        $this->assertDatabaseCount('pedidos', 1);

        $this->assertDatabaseCount(
            'pedido_estados_historial',
            1
        );
    }
}
