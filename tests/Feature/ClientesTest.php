<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConPermisos(
        string $rolNombre,
        array $permisos
    ): User {
        $rol = Role::create([
            'nombre' => $rolNombre,
            'es_sistema' => true,
        ]);

        foreach ($permisos as $nombre) {
            $permiso = Permiso::create([
                'nombre' => $nombre,
                'descripcion' => $nombre,
            ]);

            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'Usuario',
            'last_name' => 'Prueba',
            'email' => 'prueba@example.com',
            'password' => 'password123',
            'role_id' => $rol->id,
            'status' => true,
        ]);
    }

    public function test_registra_cliente_sin_correo(): void
    {
        $usuario = $this->crearUsuarioConPermisos(
            'Operaciones Comerciales',
            ['clientes.crear', 'clientes.ver']
        );

        $this->assertTrue(
        $usuario->fresh()
            ->role
            ->permisos()
            ->where('nombre', 'clientes.crear')
            ->exists(),
            'El rol de prueba no tiene clientes.crear'
        );

        $this->assertTrue(
            (bool) $usuario->fresh()->status,
            'El usuario de prueba está inactivo'
        );

        $this->actingAs($usuario);

        $respuesta = $this->post(route('clientes.store'), [
            'nombre' => 'Comercial San Juan',
            'telefono' => '999888777',
            'mercado' => 'Mercado Central',
        ]);

        $respuesta->assertRedirect();

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Comercial San Juan',
            'correo' => null,
            'estado' => 1,
        ]);
    }

    public function test_nombre_es_obligatorio(): void
    {
        $usuario = $this->crearUsuarioConPermisos(
            'Operaciones Comerciales',
            ['clientes.crear']
        );

        $this->actingAs($usuario);

        $this->post(route('clientes.store'), [
            'telefono' => '999888777',
        ])->assertSessionHasErrors('nombre');
    }

    public function test_produccion_no_puede_acceder(): void
    {
        $usuario = $this->crearUsuarioConPermisos(
            'Producción/Reparto',
            ['productos.ver']
        );

        $this->actingAs($usuario);

        $this->get(route('clientes.index'))
            ->assertForbidden();
    }

    public function test_desactivar_cliente_conserva_su_registro(): void
    {
        $usuario = $this->crearUsuarioConPermisos(
            'Administrador',
            ['clientes.cambiar_estado']
        );

        $this->actingAs($usuario);

        $cliente = Cliente::create([
            'nombre' => 'Cliente de prueba',
            'estado' => true,
        ]);

        $this->patch(
            route('clientes.cambiar-estado', $cliente)
        )->assertRedirect();

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'estado' => 0,
        ]);
    }
}
