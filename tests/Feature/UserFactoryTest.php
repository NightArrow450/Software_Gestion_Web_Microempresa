<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_usuario_valido_con_rol_sin_privilegios_y_password_cifrado(): void
    {
        $usuario = User::factory()->create()->fresh();
        $this->assertNotEmpty($usuario->first_name);
        $this->assertNotEmpty($usuario->last_name);
        $this->assertInstanceOf(Role::class, $usuario->role);
        $this->assertFalse($usuario->role->es_sistema);
        $this->assertSame(0, $usuario->role->permisos()->count());
        $this->assertTrue($usuario->status);
        $this->assertFalse($usuario->debe_cambiar_password);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertNotSame('ClavePruebaSegura123', $usuario->password);
        $this->assertTrue(Hash::check('ClavePruebaSegura123', $usuario->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('roles', 1);
    }

    public function test_conserva_unverified_rol_explicito_y_estados_sin_crear_otro_rol(): void
    {
        $rol = Role::factory()->create();
        $usuario = User::factory()->for($rol, 'role')->unverified()->create(['status' => false, 'debe_cambiar_password' => true]);
        $this->assertSame($rol->id, $usuario->role_id);
        $this->assertNull($usuario->email_verified_at);
        $this->assertFalse($usuario->status);
        $this->assertTrue($usuario->debe_cambiar_password);
        $this->assertDatabaseCount('roles', 1);
    }

    public function test_fabrica_rechaza_uso_fuera_del_entorno_de_pruebas(): void
    {
        app()->instance('env', 'production');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('solo pueden utilizarse en pruebas aisladas');
        User::factory()->create();
    }
}
