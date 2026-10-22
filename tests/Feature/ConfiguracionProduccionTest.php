<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ConfiguracionProduccionTest extends TestCase
{
    use RefreshDatabase;

    private function ruta(): void
    {
        Route::get('/diagnostico-proxy', fn (Request $request) => response()->json([
            'ip' => $request->ip(), 'https' => $request->isSecure(), 'login' => route('login'),
        ]));
    }

    public function test_http_local_y_cabeceras_de_origen_no_confiable(): void
    {
        $this->ruta();
        config(['seguridad.proxies_confiables' => '']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'suplantado.example'])
            ->get('http://localhost/diagnostico-proxy')->assertOk()->assertJsonPath('ip', '192.0.2.1')->assertJsonPath('https', false)->assertJsonPath('login', 'http://localhost/login');
    }

    public function test_asterisco_no_se_admite_fuera_de_produccion(): void
    {
        $this->ruta();
        config(['seguridad.proxies_confiables' => '*']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get('/diagnostico-proxy')->assertOk()->assertJsonPath('ip', '192.0.2.1');
    }

    public function test_host_no_puede_activar_confianza_automatica_en_proxies(): void
    {
        $this->ruta();
        config(['seguridad.proxies_confiables' => '']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->get('http://suplantado.on-forge.com/diagnostico-proxy')->assertOk()->assertJsonPath('ip', '192.0.2.1')->assertJsonPath('https', false);
    }

    public function test_proxy_cidr_confiable_reconoce_ip_https_y_urls(): void
    {
        $this->ruta();
        config(['seguridad.proxies_confiables' => '10.0.0.0/8']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.3.4'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'gestion.example'])
            ->get('/diagnostico-proxy')->assertOk()->assertJsonPath('ip', '203.0.113.9')->assertJsonPath('https', true)->assertJsonPath('login', 'https://gestion.example/login');
    }

    public function test_railway_usa_x_real_ip_e_ignora_x_forwarded_for_manipulado(): void
    {
        $this->ruta();
        $this->app->instance('env', 'production');
        config(['seguridad.proxies_confiables' => '*', 'seguridad.modo_proxy' => 'railway']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.3.4'])
            ->withHeaders(['X-Real-IP' => '203.0.113.9', 'X-Forwarded-For' => '198.51.100.99', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'gestion.example'])
            ->get('/diagnostico-proxy')->assertOk()->assertJsonPath('ip', '203.0.113.9')->assertJsonPath('https', true)->assertJsonPath('login', 'https://gestion.example/login');
    }

    public function test_railway_sin_ip_valida_no_acepta_ip_inventada(): void
    {
        $this->ruta();
        $this->app->instance('env', 'production');
        config(['seguridad.proxies_confiables' => '*', 'seguridad.modo_proxy' => 'railway']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.3.4'])
            ->withHeaders(['X-Real-IP' => 'inválida', 'X-Forwarded-For' => '198.51.100.99', 'X-Forwarded-Proto' => 'https'])
            ->get('/diagnostico-proxy')->assertOk()->assertJsonPath('ip', '10.2.3.4');
    }

    public function test_solo_produccion_redirige_http_a_https_preservando_metodo(): void
    {
        $this->get('/login')->assertOk();
        $this->app->instance('env', 'production');
        $this->post('http://localhost/login', [])->assertStatus(308)->assertRedirect('https://localhost/login');
    }

    public function test_limitador_y_log_usan_ip_real_aunque_cambie_el_proxy(): void
    {
        $this->freezeTime();
        $rol = Role::create(['nombre' => 'Administrador']);
        User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@example.com', 'password' => 'Segura12345', 'role_id' => $rol->id, 'status' => true]);
        $this->app->instance('env', 'production');
        config(['seguridad.proxies_confiables' => '*', 'seguridad.modo_proxy' => 'railway']);
        $this->withoutMiddleware(PreventRequestForgery::class);
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('seguridad')->andReturn($logger);
        $logger->shouldReceive('warning')->with('login_fallido', ['email' => 'ana@example.com', 'ip' => '203.0.113.9'])->times(5);
        $logger->shouldReceive('warning')->with('login_bloqueado', ['email' => 'ana@example.com', 'ip' => '203.0.113.9'])->once();
        $this->withHeaders(['X-Real-IP' => '203.0.113.9', 'X-Forwarded-Proto' => 'https']);
        for ($intento = 0; $intento < 5; $intento++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->post('/login', ['email' => 'ana@example.com', 'password' => 'Incorrecta123'])->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.2.2'])->post('/login', ['email' => 'ana@example.com', 'password' => 'Incorrecta123'])->assertHeader('Retry-After', '60');
    }
}
