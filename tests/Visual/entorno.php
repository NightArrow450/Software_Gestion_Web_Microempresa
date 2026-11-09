<?php

use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;

if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('RUNNER_ENVIRONMENT') !== 'github-hosted'
    || PHP_OS_FAMILY !== 'Linux' || is_file(dirname(__DIR__, 2).'/.env')) {
    throw new RuntimeException('La revisión visual solo se permite en un runner temporal de GitHub sin .env.');
}
$directorio = realpath((string) getenv('RUNNER_TEMP'));
$base = getenv('VISUAL_DATABASE');
if (! $directorio || $base !== $directorio.'/pr49-visual.sqlite' || ! getenv('VISUAL_PASSWORD')) {
    throw new RuntimeException('Identidad de la base visual no válida.');
}
foreach (['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8099',
    'APP_CONFIG_CACHE' => 'storage/framework/cache/visual_config.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $base, 'DB_URL' => '',
    'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'array',
    'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BCRYPT_ROUNDS' => '4',
    'ENVASADO_CONTROL_MATERIALES_EXACTO' => 'false'] as $clave => $valor) {
    putenv($clave.'='.$valor);
    $_ENV[$clave] = $_SERVER[$clave] = $valor;
}
require_once dirname(__DIR__, 2).'/vendor/autoload.php';
Connection::resolverFor('mysql', static function (): never {
    throw new RuntimeException('MySQL bloqueado en la revisión visual.');
});
/** @param PDO|Closure $pdo */
Connection::resolverFor('sqlite', static function ($pdo, string $nombre, string $prefijo, array $configuracion) use ($base): SQLiteConnection {
    if ($nombre !== $base) {
        throw new RuntimeException('Conexión distinta a la base visual temporal.');
    }

    return new SQLiteConnection($pdo, $nombre, $prefijo, $configuracion);
});
