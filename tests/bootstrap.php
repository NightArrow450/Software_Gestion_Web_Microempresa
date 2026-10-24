<?php

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\SQLiteConnection;

require_once dirname(__DIR__).'/vendor/autoload.php';

$variables = [
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/dhacer_config_pruebas_'.getmypid().'.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BCRYPT_ROUNDS' => '4',
];
foreach ($variables as $nombre => $valor) {
    putenv($nombre.'='.$valor);
    $_ENV[$nombre] = $_SERVER[$nombre] = $valor;
}

/** @param PDO|Closure $pdo
 * @param  array<string, mixed>  $configuracion
 */
Connection::resolverFor('mysql', static function ($pdo, string $base, string $prefijo, array $configuracion): MySqlConnection {
    if (getenv('MYSQL_TEST_ENABLE') !== '1' || ! preg_match('/\Adhacer_eco_limp_test_[a-z0-9_]+\z/', $base)) {
        throw new LogicException('Conexión MySQL bloqueada: solo se permiten bases independientes de pruebas habilitadas expresamente.');
    }

    return new MySqlConnection($pdo, $base, $prefijo, $configuracion);
});

/** @param PDO|Closure $pdo
 * @param  array<string, mixed>  $configuracion
 */
Connection::resolverFor('sqlite', static function ($pdo, string $base, string $prefijo, array $configuracion): SQLiteConnection {
    if ($base !== ':memory:') {
        throw new LogicException('Conexión SQLite bloqueada: las pruebas y el análisis deben utilizar memoria.');
    }

    return new SQLiteConnection($pdo, $base, $prefijo, $configuracion);
});
