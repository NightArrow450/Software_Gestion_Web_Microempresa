<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class LimitadorLoginTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        $this->freezeTime();
        $rol = Role::create(['nombre' => 'Administrador']);

        return User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@example.com', 'password' => 'Segura12345', 'role_id' => $rol->id, 'status' => true]);
    }

    public function test_sexto_fallo_se_bloquea_y_no_registra_contrasena(): void
    {
        $usuario = $this->usuario();
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('seguridad')->andReturn($logger);
        $contexto = ['email' => 'ana@example.com', 'ip' => '127.0.0.1'];
        $logger->shouldReceive('warning')->with('login_fallido', $contexto)->times(5);
        $logger->shouldReceive('warning')->with('login_bloqueado', $contexto)->once();
        for ($intento = 0; $intento < 5; $intento++) {
            $this->post(route('login.authenticate'), ['email' => strtoupper($usuario->email), 'password' => 'Incorrecta123'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'Incorrecta123'])
            ->assertSessionHasErrors(['email' => 'Demasiados intentos fallidos. Intenta nuevamente en 60 segundos.'])->assertHeader('Retry-After', '60');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'Segura12345'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_login_correcto_limpia_fallos_y_no_consume_intentos(): void
    {
        $usuario = $this->usuario();
        $clave = 'login:'.hash('sha256', $usuario->email.'|127.0.0.1');
        for ($intento = 0; $intento < 4; $intento++) {
            $this->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'Incorrecta123'])->assertSessionHasErrors('email');
        }
        for ($intento = 0; $intento < 7; $intento++) {
            $this->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'Segura12345'])->assertRedirect(route('dashboard'));
            $this->assertSame(0, RateLimiter::attempts($clave));
            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }

    public function test_limite_separa_email_e_ip(): void
    {
        $usuario = $this->usuario();
        for ($intento = 0; $intento < 5; $intento++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->post(route('login.authenticate'), ['email' => 'otro@example.com', 'password' => 'Incorrecta123']);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'Segura12345'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->post(route('login.authenticate'), ['email' => 'otro@example.com', 'password' => 'Incorrecta123'])->assertHeaderMissing('Retry-After');
        $this->assertGuest();
    }
}
