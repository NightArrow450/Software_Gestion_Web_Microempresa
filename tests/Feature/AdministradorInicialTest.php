<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdministradorInicialTest extends TestCase
{
    use RefreshDatabase;

    private function configurar(): void
    {
        Role::create(['nombre' => 'Administrador']);
        config(['administrador.email' => 'Inicial@Example.com', 'administrador.password' => 'Inicial12345']);
    }

    public function test_seeder_crea_hash_y_no_cambia_hash_ni_indicador_al_repetirse(): void
    {
        $this->configurar();
        $this->seed(AdminUserSeeder::class);
        $usuario = User::firstOrFail();
        $this->assertSame('inicial@example.com', $usuario->email);
        $this->assertTrue($usuario->debe_cambiar_password);
        $this->assertTrue(Hash::check('Inicial12345', $usuario->password));
        $usuario->update(['password' => 'Modificada123', 'debe_cambiar_password' => false]);
        $hash = $usuario->fresh()->password;

        config(['administrador.password' => 'OtraInicial123']);
        $this->seed(AdminUserSeeder::class);
        $this->assertSame($hash, $usuario->fresh()->password);
        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_repetir_seeder_tambien_conserva_indicador_pendiente(): void
    {
        $this->configurar();
        $this->seed(AdminUserSeeder::class);
        $hash = User::firstOrFail()->password;
        config(['administrador.password' => 'OtraInicial123']);
        $this->seed(AdminUserSeeder::class);
        $this->assertSame($hash, User::firstOrFail()->password);
        $this->assertTrue(User::firstOrFail()->debe_cambiar_password);
    }

    public static function variablesFaltantes(): array
    {
        return [['email'], ['password']];
    }

    #[DataProvider('variablesFaltantes')]
    public function test_produccion_sin_variables_falla_sin_modificar_usuarios(string $campo): void
    {
        $this->configurar();
        config(['administrador.'.$campo => null]);
        $this->app->instance('env', 'production');
        try {
            app(AdminUserSeeder::class)->run();
            $this->fail('Debía rechazar la configuración incompleta.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ADMIN_EMAIL y ADMIN_PASSWORD', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_rechazado_en_produccion_y_no_invocado_por_seeder_normal(): void
    {
        $this->app->instance('env', 'production');
        try {
            app(DemoSeeder::class)->run();
            $this->fail('Debía impedir datos de demostración en producción.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DemoSeeder no está permitido en producción.', $exception->getMessage());
        }
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_demo_local_es_idempotente_y_admin_sin_variables_no_usa_contrasena_fija(): void
    {
        $this->configurar();
        config(['administrador.email' => null, 'administrador.password' => null]);
        $this->seed(AdminUserSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_invocacion_directa_sin_comando_ni_credenciales_no_crea_administrador(): void
    {
        config(['administrador.email' => null, 'administrador.password' => null]);
        app(AdminUserSeeder::class)->run();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_seeder_normal_no_crea_datos_demo(): void
    {
        $this->configurar();
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_primer_ingreso_exige_cambio_y_solo_password_valida_libera_la_cuenta(): void
    {
        $this->configurar();
        $this->seed(AdminUserSeeder::class);
        $usuario = User::firstOrFail();
        $this->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'Inicial12345'])->assertRedirect(route('password.cambiar'));
        $this->get(route('dashboard'))->assertRedirect(route('password.cambiar'));
        $this->get(route('password.cambiar'))->assertOk()->assertSee('Actualiza tu contraseña');
        $this->put(route('password.actualizar'), ['password_actual' => 'Incorrecta123', 'password' => 'Modificada123', 'password_confirmation' => 'Modificada123'])
            ->assertSessionHasErrors('password_actual')->assertSessionMissing('_old_input.password_actual')->assertSessionMissing('_old_input.password');
        $this->assertTrue($usuario->fresh()->debe_cambiar_password);
        $this->put(route('password.actualizar'), ['password_actual' => 'Inicial12345', 'password' => 'Modificada123', 'password_confirmation' => 'Modificada123'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
        $this->assertTrue(Hash::check('Modificada123', $usuario->fresh()->password));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_no_acepta_reutilizar_password_inicial_y_permite_logout(): void
    {
        $this->configurar();
        $this->seed(AdminUserSeeder::class);
        $this->actingAs(User::firstOrFail())->put(route('password.actualizar'), ['password_actual' => 'Inicial12345', 'password' => 'Inicial12345', 'password_confirmation' => 'Inicial12345'])
            ->assertSessionHasErrors('password');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('password.cambiar'))->assertRedirect(route('login'));
    }

    public function test_migracion_preserva_contrasenas_y_cuentas_existentes(): void
    {
        $this->configurar();
        $this->seed(AdminUserSeeder::class);
        $usuario = User::firstOrFail();
        $hash = $usuario->password;
        $migracion = require database_path('migrations/2026_10_22_150654_add_debe_cambiar_password_to_users_table.php');
        $migracion->down();
        $migracion->up();
        $this->assertSame($hash, $usuario->fresh()->password);
        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
        $this->assertDatabaseCount('users', 1);
    }
}
