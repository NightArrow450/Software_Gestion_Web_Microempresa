<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EntornoPruebasSeguroTest extends TestCase
{
    public function test_arranque_fuerza_sqlite_en_memoria_y_servicios_aislados(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', DB::getDefaultConnection());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('array', config('cache.default'));
    }

    public function test_bloquea_base_mysql_real_antes_de_abrir_pdo(): void
    {
        config(['database.connections.prohibida' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'software_gestion_web_microempresa', 'username' => 'no_conectar', 'password' => '']]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Conexión MySQL bloqueada');
        DB::connection('prohibida');
    }

    public function test_bloquea_sqlite_en_archivo_antes_de_abrir_pdo(): void
    {
        config(['database.connections.prohibida' => ['driver' => 'sqlite', 'database' => sys_get_temp_dir().'/no_abrir_dhacer.sqlite']]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Conexión SQLite bloqueada');
        DB::connection('prohibida');
    }

    public function test_arranques_concurrentes_no_comparten_cache_con_la_aplicacion_ni_entre_procesos(): void
    {
        $codigo = <<<'PHP'
require "tests/bootstrap.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    $app->getCachedConfigPath(), $app->getCachedPackagesPath(), $app->getCachedServicesPath(),
    $app->getCachedRoutesPath(), $app->getCachedEventsPath(),
], JSON_THROW_ON_ERROR);
PHP;
        $primero = new Process([PHP_BINARY, '-r', $codigo], base_path());
        $segundo = new Process([PHP_BINARY, '-r', $codigo], base_path());
        $primero->start();
        $segundo->start();
        $this->assertSame(0, $primero->wait(), $primero->getErrorOutput());
        $this->assertSame(0, $segundo->wait(), $segundo->getErrorOutput());
        $rutasPrimero = json_decode($primero->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $rutasSegundo = json_decode($segundo->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($rutasPrimero as $indice => $ruta) {
            $this->assertNotSame($ruta, $rutasSegundo[$indice]);
            $this->assertStringStartsWith(str_replace('\\', '/', storage_path('framework/cache')), str_replace('\\', '/', $ruta));
            $this->assertStringStartsWith(str_replace('\\', '/', storage_path('framework/cache')), str_replace('\\', '/', $rutasSegundo[$indice]));
        }
    }
}
