<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class PaginasErrorTest extends TestCase
{
    use RefreshDatabase;

    public static function errores(): array
    {
        return [
            [403, 'Acceso no autorizado'],
            [404, 'Página no encontrada'],
            [419, 'Sesión vencida'],
            [500, 'No pudimos completar la solicitud'],
            [503, 'Servicio no disponible'],
        ];
    }

    #[DataProvider('errores')]
    public function test_paginas_son_espanolas_y_no_exponen_excepcion_incluso_con_debug(int $codigo, string $texto): void
    {
        config(['app.debug' => true]);
        Route::get('/probar-error', fn () => abort($codigo, 'secreto-interno-no-publico'));
        $this->get('/probar-error')->assertStatus($codigo)->assertSee($texto)->assertSee('DHACER ECO LIMP')
            ->assertDontSee('secreto-interno-no-publico')->assertDontSee('Stack trace')->assertDontSee('HttpException');
    }

    public function test_url_inexistente_muestra_404_con_el_diseno_del_sistema(): void
    {
        $this->get('/pagina-que-no-existe')->assertNotFound()->assertSee('Página no encontrada')->assertSee('css/errors.css');
    }

    public function test_500_registra_solo_contexto_seguro_y_respuesta_json_sin_trazas(): void
    {
        $rol = Role::create(['nombre' => 'Administrador']);
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'error@example.com', 'password' => 'Segura12345', 'role_id' => $rol->id, 'status' => true]);
        Route::post('/probar-500', function (): void {
            throw new RuntimeException('SQL con password=secreto-oculto');
        })->name('prueba.error');
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('seguridad')->once()->andReturn($logger);
        $logger->shouldReceive('error')->once()->with('error_servidor', ['usuario_id' => $usuario->id, 'ruta' => 'prueba.error', 'metodo' => 'POST', 'excepcion' => RuntimeException::class]);

        $this->actingAs($usuario)->postJson('/probar-500?token=secreto-query', ['password' => 'secreto-input'])
            ->assertStatus(500)->assertExactJson(['mensaje' => 'No pudimos completar la solicitud.']);
    }
}
