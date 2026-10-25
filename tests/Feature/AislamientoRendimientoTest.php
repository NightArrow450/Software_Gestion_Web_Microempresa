<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Performance\AutorizacionRendimiento;
use Tests\TestCase;

class AislamientoRendimientoTest extends TestCase
{
    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function escenario(): array
    {
        $m = ['autorizacion' => 'Prueba simulada, no autoriza infraestructura', 'vence' => '2999-01-01T00:00:00Z',
            'checkout' => 'C:/aislado', 'operativo' => 'C:/operativo', 'base' => 'dhacer_eco_limp_demo_f9b_simulada', 'usuario' => 'demo_f9b_simulada',
            'url' => 'http://127.0.0.1:8089', 'mysql_puerto' => 3313, 'mysql_pid' => 123, 'apache_pid' => 456,
            'mysql_datadir' => 'C:/privado/mysql', 'mysql_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'mysql_binario' => 'C:/bin/mysqld.exe',
            'apache_binario' => 'C:/bin/httpd.exe', 'apache_config' => 'C:/privado/httpd.conf', 'mysql_argumentos_sha256' => str_repeat('a', 64),
            'apache_argumentos_sha256' => str_repeat('b', 64), 'apache_config_sha256' => str_repeat('c', 64), 'operaciones' => ['verificar']];
        $c = ['checkout' => $m['checkout'], 'operativo' => $m['operativo'], 'base' => $m['base'], 'usuario' => $m['usuario'], 'host' => '127.0.0.1', 'puerto' => 3313, 'app_url' => $m['url']];

        return [$m, $c];
    }

    public function test_metadatos_simulados_coincidentes_superan_solo_la_validacion_pura(): void
    {
        $this->expectNotToPerformAssertions();
        [$m, $c] = $this->escenario();
        AutorizacionRendimiento::validarConfiguracion($m, 'verificar', $c);
        AutorizacionRendimiento::validarIdentidad($m, ['base' => $m['base'], 'version' => '8.4.3', 'puerto' => 3313, 'directorio' => 'C:\\privado\\mysql\\', 'uuid' => $m['mysql_uuid'], 'cuenta' => $m['usuario'].'@localhost', 'roles' => 'NONE']);
    }

    /** @return array<string, array{string, mixed}> */
    public static function destinosInvalidos(): array
    {
        return ['base real' => ['base', 'software_gestion_web_microempresa'], 'respaldo' => ['base', 'ecolimp_respaldo_prueba'],
            'puerto operativo' => ['mysql_puerto', 3306], 'root' => ['usuario', 'root'], 'url operativa' => ['url', 'http://127.0.0.1:8000'],
            'externa' => ['url', 'https://example.com:8449'], 'credenciales URL' => ['url', 'http://usuario@127.0.0.1:8089'],
            'checkout operativo' => ['checkout', 'C:/operativo'], 'pid ausente' => ['mysql_pid', 0], 'huella falsa' => ['apache_config_sha256', ''],
            'caducado' => ['vence', '2000-01-01'], 'sin autorización de operación' => ['operaciones', ['cargar']]];
    }

    #[DataProvider('destinosInvalidos')]
    public function test_bloquea_destinos_y_manifiestos_invalidos_sin_conectar(string $campo, mixed $valor): void
    {
        [$m, $c] = $this->escenario();
        $m[$campo] = $valor;
        $this->expectException(RuntimeException::class);
        AutorizacionRendimiento::validarConfiguracion($m, 'verificar', $c);
    }

    /** @return array<string, array{string, mixed}> */
    public static function identidadesInvalidas(): array
    {
        return ['otra base' => ['base', 'ecolimp_respaldo_prueba'], 'otro puerto' => ['puerto', 3306], 'otro datadir' => ['directorio', 'C:/laragon/data/mysql'],
            'otro UUID' => ['uuid', 'distinto'], 'otro usuario' => ['cuenta', 'root@localhost'], 'rol heredado' => ['roles', '`administrador`@`%`'], 'otra versión' => ['version', '8.0.0']];
    }

    #[DataProvider('identidadesInvalidas')]
    public function test_rechaza_identidad_efectiva_distinta(string $campo, mixed $valor): void
    {
        [$m] = $this->escenario();
        $i = ['base' => $m['base'], 'version' => '8.4.3', 'puerto' => 3313, 'directorio' => $m['mysql_datadir'], 'uuid' => $m['mysql_uuid'], 'cuenta' => $m['usuario'].'@localhost', 'roles' => 'NONE'];
        $i[$campo] = $valor;
        $this->expectException(RuntimeException::class);
        AutorizacionRendimiento::validarIdentidad($m, $i);
    }

    public function test_puerta_de_carga_sin_manifiesto_no_consulta_ni_escribe_en_la_base(): void
    {
        $previo = getenv('RENDIMIENTO_MANIFIESTO');
        putenv('RENDIMIENTO_MANIFIESTO');
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            $consultas[] = $consulta->sql;
        });
        try {
            (new AutorizacionRendimiento)->verificar('cargar');
            $this->fail('La puerta de carga debe detenerse antes de acceder a la base.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('manifiesto privado', $error->getMessage());
            $this->assertSame([], $consultas);
        } finally {
            $previo === false ? putenv('RENDIMIENTO_MANIFIESTO') : putenv('RENDIMIENTO_MANIFIESTO='.$previo);
        }
    }
}
