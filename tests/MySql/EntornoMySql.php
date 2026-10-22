<?php

namespace Tests\MySql;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;

class EntornoMySql
{
    public static function proteger(): array
    {
        $conexion = config('database.connections.mysql_concurrencia');
        $nombre = $conexion['database'];
        $local = is_file(base_path('.env')) ? Dotenv::parse(file_get_contents(base_path('.env'))) : [];
        if (getenv('MYSQL_TEST_ENABLE') !== '1' || ! preg_match('/\\Adhacer_eco_limp_test_[a-z0-9_]+\\z/', $nombre) || strcasecmp($nombre, $local['DB_DATABASE'] ?? '') === 0 || $conexion['username'] === '') {
            throw new \RuntimeException('Prueba bloqueada: configura explícitamente una base MySQL independiente con prefijo dhacer_eco_limp_test_ y MYSQL_TEST_ENABLE=1.');
        }

        return $conexion;
    }

    public static function conectar(): void
    {
        self::proteger();
        DB::purge('mysql_concurrencia');
        DB::setDefaultConnection('mysql_concurrencia');
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        app()->instance('env', 'testing');
    }
}
