<?php

namespace Tests\MySql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\PruebasCierreSurtidos;

class CierreEsquemaSurtidosMySqlTest extends PruebaInventarioMySql
{
    use PruebasCierreSurtidos;

    private function prepararBaseCierre(string $caso): void
    {
        $conexion = EntornoMySql::proteger();
        $conexion['database'] .= '_c_'.$caso;
        $nombre = $conexion['database'];
        $this->assertLessThanOrEqual(64, strlen($nombre));
        config(['database.connections.mysql_concurrencia' => $conexion]);
        EntornoMySql::proteger();
        $servidor = new PDO('mysql:host='.$conexion['host'].';port='.$conexion['port'].';charset=utf8mb4', $conexion['username'], $conexion['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $servidor->exec("CREATE DATABASE `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        EntornoMySql::conectar();
        Schema::clearResolvedInstance('db.schema');
        $this->assertSame($nombre, DB::getDatabaseName());
        $this->assertSame($nombre, Schema::getConnection()->getDatabaseName());
        $this->assertStringStartsWith('8.4.', DB::selectOne('SELECT VERSION() version')->version);
    }
}
