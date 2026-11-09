<?php

namespace Tests\Feature;

use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class ReintentoTransaccionIdempotenteTest extends TestCase
{
    use EnvasadoEscenario;

    public static function conflictos(): array
    {
        return ['edición recuperable' => ['pedidos.update', 1, 302, 2, 1], 'edición agota tres intentos' => ['pedidos.update', 10, 500, 3, 0],
            'acción sensible recuperable' => ['roles.prueba', 1, 302, 2, 1], 'acción sensible agota tres intentos' => ['roles.prueba', 10, 500, 3, 0]];
    }

    #[DataProvider('conflictos')]
    public function test_reintenta_conflicto_anidado_sin_duplicar_y_respeta_el_limite(string $nombre, int $fallos, int $estado, int $intentosEsperados, int $solicitudes): void
    {
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', DB::getDatabaseName());
        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertExitCode(0);
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e)->fresh();
        $antes = [];
        foreach (['users', 'roles', 'permisos', 'rol_permiso'] as $tabla) {
            $antes[$tabla] = DB::table($tabla)->get()->toArray();
        }
        $intentos = 0;
        Route::post('/_pr45/reintento/{pedido}', function () use (&$intentos, $fallos) {
            $intentos++;
            if ($intentos <= $fallos) {
                return response('', 500)->withException(new DeadlockException('Deadlock found when trying to get lock'));
            }

            return redirect('/login');
        })->middleware('web')->defaults('idempotente', true)->name($nombre);

        $respuesta = $this->actingAs($e['usuario'])->post('/_pr45/reintento/'.$pedido->id, ['_version' => $pedido->version, '_updated_at' => $pedido->getRawOriginal('updated_at')]);

        $respuesta->assertStatus($estado);
        $this->assertSame($intentosEsperados, $intentos);
        $this->assertDatabaseCount('solicitudes_idempotentes', $solicitudes);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame(0, DB::transactionLevel());
        foreach ($antes as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
    }
}
