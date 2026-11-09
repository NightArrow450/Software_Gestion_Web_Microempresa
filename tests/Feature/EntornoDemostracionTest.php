<?php

namespace Tests\Feature;

use App\Services\EntornoDemostracion;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EntornoDemostracionTest extends TestCase
{
    private function configurar(): void
    {
        $this->app['env'] = 'demo';
        config([
            'demostracion.activa' => true,
            'demostracion.proyecto_operativo' => dirname(base_path()),
            'demostracion.servidor_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'demostracion.puerto' => 3313,
            'demostracion.datos_sinteticos_confirmados' => true,
            'database.default' => 'demo_controlada',
            'database.connections.demo_controlada' => [
                'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3313,
                'database' => 'dhacer_eco_limp_demo_video', 'username' => 'demo_video',
                'password' => 'credencial-ficticia-solo-del-doble-de-prueba',
            ],
        ]);
    }

    public static function conexionesProhibidas(): array
    {
        return [
            'base real' => ['database.connections.demo_controlada.database', 'software_gestion_web_microempresa'],
            'respaldo' => ['database.connections.demo_controlada.database', 'ecolimp_respaldo_prueba'],
            'servidor externo' => ['database.connections.demo_controlada.host', '192.0.2.1'],
            'puerto operativo' => ['database.connections.demo_controlada.port', 3306],
            'puerto diferente' => ['database.connections.demo_controlada.port', 3314],
            'cuenta administradora' => ['database.connections.demo_controlada.username', 'root'],
            'sin contraseña' => ['database.connections.demo_controlada.password', ''],
            'URL alternativa' => ['database.connections.demo_controlada.url', 'mysql://ejemplo.invalid'],
            'lectura alternativa' => ['database.connections.demo_controlada.read', ['host' => '192.0.2.2']],
            'socket' => ['database.connections.demo_controlada.unix_socket', '/tmp/mysql.sock'],
            'UUID no confirmado' => ['demostracion.servidor_uuid', ''],
        ];
    }

    #[DataProvider('conexionesProhibidas')]
    public function test_conexion_no_permitida_se_rechaza_antes_de_abrir_mysql(string $clave, mixed $valor): void
    {
        $this->configurar();
        config([$clave => $valor]);
        DB::shouldReceive('connection')->never();

        $this->expectException(RuntimeException::class);
        app(EntornoDemostracion::class)->verificar();
    }

    public function test_checkout_operativo_se_rechaza_sin_conexion(): void
    {
        $this->configurar();
        config(['demostracion.proyecto_operativo' => base_path()]);
        DB::shouldReceive('connection')->never();

        $this->expectException(RuntimeException::class);
        app(EntornoDemostracion::class)->verificar();
    }

    public static function concesiones(): array
    {
        return [
            'solo base literal' => ['GRANT SELECT, INSERT, UPDATE, DELETE ON `dhacer\\_eco\\_limp\\_demo\\_video`.* TO \'demo_video\'@\'localhost\'', true],
            'administracion global' => ['GRANT ALL PRIVILEGES ON *.* TO \'demo_video\'@\'localhost\'', false],
            'otra base' => ['GRANT SELECT ON `ecolimp_respaldo_prueba`.* TO \'demo_video\'@\'localhost\'', false],
            'patron de bases' => ['GRANT SELECT ON `dhacer_eco_limp_demo_%`.* TO \'demo_video\'@\'localhost\'', false],
            'delegacion' => ['GRANT SELECT ON `dhacer\\_eco\\_limp\\_demo\\_video`.* TO \'demo_video\'@\'localhost\' WITH GRANT OPTION', false],
            'rol heredado' => ['GRANT \'rol_amplio\'@\'%\' TO \'demo_video\'@\'localhost\'', false],
            'nombre literal con revocacion parcial' => ['GRANT SELECT ON `dhacer_eco_limp_demo_video`.* TO \'demo_video\'@\'localhost\'', true, true],
        ];
    }

    #[DataProvider('concesiones')]
    public function test_privilegios_efectivos_no_permiten_acceso_a_otras_bases(string $concesion, bool $permitida, bool $revocaciones = false): void
    {
        $this->configurar();
        $conexion = Mockery::mock(ConnectionInterface::class);
        DB::shouldReceive('connection')->once()->andReturn($conexion);
        $conexion->shouldReceive('selectOne')->once()->andReturn((object) [
            'base' => 'dhacer_eco_limp_demo_video', 'puerto' => 3313,
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'cuenta' => 'demo_video@localhost',
            'version' => '8.4.3', 'revocaciones_parciales' => (int) $revocaciones,
        ]);
        $conexion->shouldReceive('select')->once()->with('SHOW GRANTS FOR CURRENT_USER')->andReturn([(object) ['concesion' => $concesion]]);
        if (! $permitida) {
            $this->expectException(RuntimeException::class);
        }

        $resultado = app(EntornoDemostracion::class)->verificar();

        $this->assertSame(['base' => 'dhacer_eco_limp_demo_video', 'puerto' => 3313], $resultado);
    }

    public function test_bandera_incorrecta_bloquea_http_antes_de_sesiones_y_no_expone_secretos(): void
    {
        config(['demostracion.activa' => true]);
        DB::shouldReceive('connection')->never();

        $this->get('/login')->assertStatus(503)->assertSee('Demostración bloqueada')->assertDontSee('DB_PASSWORD');
    }

    public function test_uuid_efectivo_diferente_rechaza_antes_de_consultar_privilegios(): void
    {
        $this->configurar();
        $conexion = Mockery::mock(ConnectionInterface::class);
        DB::shouldReceive('connection')->once()->andReturn($conexion);
        $conexion->shouldReceive('selectOne')->once()->andReturn((object) [
            'base' => 'dhacer_eco_limp_demo_video', 'puerto' => 3313,
            'uuid' => 'ffffffff-bbbb-cccc-dddd-eeeeeeeeeeee', 'cuenta' => 'demo_video@localhost',
            'version' => '8.4.3', 'revocaciones_parciales' => 0,
        ]);
        $conexion->shouldReceive('select')->never();

        $this->expectException(RuntimeException::class);
        app(EntornoDemostracion::class)->verificar();
    }

    public function test_procedencia_no_confirmada_no_habilita_demostracion(): void
    {
        $this->partialMock(EntornoDemostracion::class)->shouldReceive('verificar')->once()->andReturn(['base' => 'dhacer_eco_limp_demo_video', 'puerto' => 3313]);
        config(['demostracion.datos_sinteticos_confirmados' => false]);

        $this->expectException(RuntimeException::class);
        app(EntornoDemostracion::class)->verificarDatosSinteticos();
    }

    public function test_aplicacion_normal_no_muestra_rotulo_de_demostracion(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('DEMOSTRACIÓN');
    }

    public function test_rotulo_solo_se_muestra_tras_la_validacion_del_entorno(): void
    {
        config(['demostracion.activa' => true]);
        $this->mock(EntornoDemostracion::class)->shouldReceive('verificarDatosSinteticos')->once();

        $this->get('/login')->assertOk()->assertSee('DEMOSTRACIÓN · Datos ficticios');
    }

    public function test_comando_falla_sin_exponer_credenciales_ni_inicializar_datos(): void
    {
        DB::shouldReceive('connection')->never();

        $this->artisan('demostracion:verificar')->expectsOutputToContain('Verificación rechazada')->assertFailed();
    }
}
