<?php

namespace Tests\MySql;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class PruebaEntregaMySql extends TestCase
{
    private bool $transaccionDePrueba = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MYSQL_TEST_ENABLE') !== '1') {
            $this->markTestSkipped('PENDIENTE DE EJECUCIÓN: las devoluciones requieren MySQL independiente.');
        }
        $configuracion = EntornoMySql::proteger();
        $configuracion['database'] .= '_funcional';
        config(['database.connections.mysql_concurrencia' => $configuracion]);
        EntornoMySql::proteger();
        $nombre = $configuracion['database'];
        if (strlen($nombre) > 64) {
            throw new \RuntimeException('Nombre de base de prueba demasiado largo.');
        }
        $servidor = new \PDO('mysql:host='.$configuracion['host'].';port='.$configuracion['port'].';charset=utf8mb4', $configuracion['username'], $configuracion['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $servidor->exec("CREATE DATABASE IF NOT EXISTS `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        EntornoMySql::conectar();
        if (DB::connection()->getDatabaseName() !== $nombre) {
            throw new \RuntimeException('Conexión de prueba inesperada.');
        }
        if (Schema::getTableListing($nombre) !== [] && (! Schema::hasTable('__entregas_pruebas') || ! DB::table('__entregas_pruebas')->where('propietario', 'DHACER_ENTREGAS_PRUEBAS')->exists())) {
            throw new \RuntimeException('La base contiene datos ajenos: no se modificará.');
        }
        DB::statement('CREATE TABLE IF NOT EXISTS __entregas_pruebas (propietario VARCHAR(80) PRIMARY KEY)');
        DB::table('__entregas_pruebas')->insertOrIgnore(['propietario' => 'DHACER_ENTREGAS_PRUEBAS']);
        if (Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--force' => true, '--no-interaction' => true]) !== 0) {
            throw new \RuntimeException('No se pudo preparar el esquema de prueba.');
        }
        DB::beginTransaction();
        $this->transaccionDePrueba = true;
    }

    protected function tearDown(): void
    {
        if ($this->transaccionDePrueba) {
            EntornoMySql::proteger();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        parent::tearDown();
    }
}
