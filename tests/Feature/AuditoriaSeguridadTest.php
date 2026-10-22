<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class AuditoriaSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol = 'Administrador', string $email = 'admin@example.com'): User
    {
        $role = Role::firstOrCreate(['nombre' => $rol]);

        return User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => $email, 'password' => 'Segura12345', 'role_id' => $role->id, 'status' => true]);
    }

    private function capturar(array &$eventos): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('seguridad')->andReturn($logger);
        $logger->shouldReceive('info')->andReturnUsing(function (string $evento, array $contexto) use (&$eventos): void {
            $eventos[] = ['evento' => $evento, 'contexto' => $contexto];
        });
    }

    public function test_login_y_logout_registran_identidad_sin_contrasenas(): void
    {
        $usuario = $this->usuario();
        $eventos = [];
        $this->capturar($eventos);
        $this->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'Segura12345', '_token' => 'token-privado'])->assertRedirect();
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertSame(['login_correcto', 'logout'], array_column($eventos, 'evento'));
        $this->assertSame($usuario->id, $eventos[0]['contexto']['usuario_id']);
        $this->assertSame('127.0.0.1', $eventos[0]['contexto']['ip']);
        $this->assertStringNotContainsString('Segura12345', json_encode($eventos));
        $this->assertStringNotContainsString('token-privado', json_encode($eventos));
    }

    public function test_cambios_de_rol_y_estado_de_usuario_quedan_auditados(): void
    {
        $admin = $this->usuario();
        $usuario = $this->usuario('Operaciones Comerciales', 'operador@example.com');
        $nuevoRol = Role::create(['nombre' => 'Producción/Reparto']);
        $eventos = [];
        $this->capturar($eventos);
        $this->actingAs($admin)->put(route('usuarios.update', $usuario), ['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => $usuario->email, 'role_id' => $nuevoRol->id, 'status' => 0])->assertSessionHasNoErrors();
        $this->patch(route('usuarios.cambiar-estado', $usuario))->assertRedirect();

        $this->assertSame(['usuario_rol_cambiado', 'usuario_estado_cambiado', 'usuario_estado_cambiado'], array_column($eventos, 'evento'));
        $this->assertSame($admin->id, $eventos[0]['contexto']['usuario_id']);
        $this->assertSame($usuario->id, $eventos[0]['contexto']['usuario_afectado_id']);
        $this->assertFalse($eventos[1]['contexto']['estado']);
        $this->assertTrue($eventos[2]['contexto']['estado']);
        $this->assertSame($nuevoRol->id, $usuario->fresh()->role_id);
    }

    public function test_alta_edicion_y_baja_de_roles_y_permisos_se_registran(): void
    {
        $admin = $this->usuario();
        $permiso = Permiso::firstOrCreate(['nombre' => 'pedidos.ver']);
        $eventos = [];
        $this->capturar($eventos);
        $this->actingAs($admin)->post(route('roles.store'), ['nombre' => 'Consulta', 'permisos' => [$permiso->id]])->assertSessionHasNoErrors();
        $rol = Role::where('nombre', 'Consulta')->firstOrFail();
        $this->put(route('roles.update', $rol), ['nombre' => 'Consulta', 'permisos' => []])->assertSessionHasNoErrors();
        $this->delete(route('roles.destroy', $rol))->assertRedirect();
        $this->post(route('permisos.store'), ['nombre' => 'pedidos.prueba', 'descripcion' => 'Prueba de auditoría'])->assertSessionHasNoErrors();
        $nuevo = Permiso::where('nombre', 'pedidos.prueba')->firstOrFail();
        $this->put(route('permisos.update', $nuevo), ['nombre' => 'pedidos.prueba', 'descripcion' => 'Actualizado'])->assertSessionHasNoErrors();
        $this->delete(route('permisos.destroy', $nuevo))->assertRedirect();

        $this->assertSame(['rol_creado', 'rol_actualizado', 'rol_eliminado', 'permiso_creado', 'permiso_actualizado', 'permiso_eliminado'], array_column($eventos, 'evento'));
        $this->assertSame([$permiso->id], $eventos[1]['contexto']['permisos_anteriores']);
        $this->assertSame([], $eventos[1]['contexto']['permisos_nuevos']);
        $this->assertDatabaseMissing('roles', ['id' => $rol->id]);
        $this->assertDatabaseMissing('permisos', ['id' => $nuevo->id]);
    }

    public function test_acceso_403_se_registra_sin_cuerpo_ni_cabeceras_sensibles(): void
    {
        $usuario = $this->usuario('Producción/Reparto');
        $eventos = [];
        $this->capturar($eventos);
        $this->actingAs($usuario)->withHeaders(['Authorization' => 'Bearer secreto-de-prueba'])
            ->get(route('usuarios.index', ['token' => 'valor-privado']))->assertForbidden();

        $this->assertCount(1, $eventos);
        $this->assertSame('acceso_denegado', $eventos[0]['evento']);
        $this->assertSame('usuarios.index', $eventos[0]['contexto']['ruta']);
        $this->assertSame($usuario->id, $eventos[0]['contexto']['usuario_id']);
        $this->assertStringNotContainsString('secreto-de-prueba', json_encode($eventos));
        $this->assertStringNotContainsString('valor-privado', json_encode($eventos));
    }
}
