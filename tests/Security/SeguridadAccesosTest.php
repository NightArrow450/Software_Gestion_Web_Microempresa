<?php

namespace Tests\Security;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeguridadAccesosTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(
        string $nombreRol = 'Producción/Reparto',
        array $permisos = [],
        bool $activo = true
    ): User {
        $rol = Role::create([
            'nombre' => $nombreRol,
            'es_sistema' => true,
        ]);

        foreach ($permisos as $nombrePermiso) {
            $permiso = Permiso::firstOrCreate(
                ['nombre' => $nombrePermiso],
                ['descripcion' => $nombrePermiso]
            );

            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'Usuario',
            'last_name' => 'Seguridad',
            'email' => 'seguridad@local.test',
            'password' => 'Segura12345',
            'role_id' => $rol->id,
            'status' => $activo,
        ]);
    }

    public function test_ps01_visitante_no_puede_consultar_pedidos(): void
    {
        $this->get(route('pedidos.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_ps02_visitante_no_puede_registrar_pedidos(): void
    {
        $this->post(route('pedidos.store'), [])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_ps03_no_administrador_no_puede_gestionar_usuarios(): void
    {
        $usuario = $this->crearUsuario('Producción/Reparto', [
            'pedidos.ver',
        ]);

        $this->actingAs($usuario)
            ->get(route('usuarios.index'))
            ->assertForbidden();
    }

    public function test_ps04_produccion_no_puede_ver_clientes_sin_permiso(): void
    {
        $usuario = $this->crearUsuario('Producción/Reparto', [
            'pedidos.ver',
        ]);

        $this->actingAs($usuario)
            ->get(route('clientes.index'))
            ->assertForbidden();
    }

    public function test_ps05_sin_permiso_crear_pedidos_no_puede_registrarlos(): void
    {
        $usuario = $this->crearUsuario('Operaciones Comerciales', [
            'pedidos.ver',
        ]);

        $this->actingAs($usuario)
            ->post(route('pedidos.store'), [])
            ->assertForbidden();

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_ps06_sin_permiso_ajustar_inventario_no_puede_crear_insumos(): void
    {
        $usuario = $this->crearUsuario('Producción/Reparto', [
            'inventario.ver',
        ]);

        $this->actingAs($usuario)
            ->get(route('inventario.insumos.create'))
            ->assertForbidden();
    }

    public function test_ps07_usuario_con_permiso_puede_consultar_pedidos(): void
    {
        $usuario = $this->crearUsuario('Operaciones Comerciales', [
            'pedidos.ver',
        ]);

        $this->actingAs($usuario)
            ->get(route('pedidos.index'))
            ->assertOk();
    }

    public function test_ps08_login_rechaza_contrasena_incorrecta(): void
    {
        $usuario = $this->crearUsuario('Operaciones Comerciales');

        $this->post(route('login.authenticate'), [
            'email' => $usuario->email,
            'password' => 'ContrasenaErronea123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ps09_login_rechaza_cuenta_inactiva(): void
    {
        $usuario = $this->crearUsuario('Operaciones Comerciales', [], false);

        $this->post(route('login.authenticate'), [
            'email' => $usuario->email,
            'password' => 'Segura12345',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ps10_las_contrasenas_se_almacenan_como_hash(): void
    {
        $usuario = $this->crearUsuario('Administrador');
        $hashAlmacenado = $usuario->fresh()->password;

        $this->assertNotSame('Segura12345', $hashAlmacenado);
        $this->assertTrue(Hash::check('Segura12345', $hashAlmacenado));
    }

    public function test_ps11_cerrar_sesion_revoca_la_autenticacion(): void
    {
        $usuario = $this->crearUsuario('Operaciones Comerciales', [
            'pedidos.ver',
        ]);

        $this->actingAs($usuario)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->get(route('pedidos.index'))
            ->assertRedirect(route('login'));
    }
}
