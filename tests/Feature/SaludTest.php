<?php

namespace Tests\Feature;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PDOException;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class SaludTest extends TestCase
{
    public function test_salud_verifica_conexion_sin_login_cookies_ni_detalles(): void
    {
        $respuesta = $this->get('/salud')->assertOk()->assertExactJson(['estado' => 'ok'])
            ->assertHeaderMissing('Set-Cookie')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
    }

    public function test_fallo_de_conexion_devuelve_503_generico_aun_con_sesion_en_base_de_datos(): void
    {
        config(['session.driver' => 'database', 'app.debug' => true]);
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->with('SELECT 1')->once()->andThrow(new PDOException('DB_PASSWORD=secreto-interno; host=privado'));
        DB::shouldReceive('connection')->andReturn($conexion);
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('seguridad')->once()->andReturn($logger);
        $logger->shouldReceive('warning')->with('salud_no_disponible')->once();

        $this->get('/salud')->assertStatus(503)->assertExactJson(['estado' => 'no_disponible'])
            ->assertHeaderMissing('Set-Cookie')->assertDontSee('secreto-interno')->assertDontSee('privado');
    }

    public function test_comprobacion_interna_de_railway_usa_http_sin_redireccion(): void
    {
        $this->app->instance('env', 'production');
        $this->get('http://localhost/salud')->assertOk()->assertExactJson(['estado' => 'ok']);
    }

    public function test_up_existente_se_conserva(): void
    {
        $this->get('/up')->assertOk();
    }
}
