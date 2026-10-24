<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use LogicException;
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
}
