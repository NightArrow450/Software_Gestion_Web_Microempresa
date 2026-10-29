<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\MySql\EntornoMySql;

require_once dirname(__DIR__).'/bootstrap.php';

$directorio = getenv('DHACER_RED_ENV');
if (! $directorio || ! is_dir($directorio) || is_file(dirname(__DIR__, 2).'/.env')) {
    throw new RuntimeException('Falta el manifiesto privado o el checkout contiene un .env operativo.');
}
$manifiesto = json_decode(file_get_contents($directorio.'/manifiesto.json'), true, 512, JSON_THROW_ON_ERROR);
$privadas = json_decode(file_get_contents($directorio.'/credenciales-privadas.json'), true, 512, JSON_THROW_ON_ERROR);
if (realpath($manifiesto['checkout']) !== realpath(dirname(__DIR__, 2)) || $manifiesto['escucha'] !== '127.0.0.1'
    || (int) $manifiesto['puerto'] === 3306 || ! str_starts_with($manifiesto['base_visual'], 'dhacer_eco_limp_test_visual_')) {
    throw new RuntimeException('Identidad del checkout, puerto o base no permitida.');
}
$administrar = PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'preparar';
foreach (['MYSQL_TEST_ENABLE' => '1', 'MYSQL_TEST_HOST' => '127.0.0.1', 'MYSQL_TEST_PORT' => (string) $manifiesto['puerto'],
    'MYSQL_TEST_DATABASE' => $manifiesto['base_visual'], 'MYSQL_TEST_USERNAME' => $administrar ? 'root' : $privadas['usuario_visual'],
    'MYSQL_TEST_PASSWORD' => $administrar ? $privadas['root'] : $privadas['visual']] as $nombre => $valor) {
    putenv($nombre.'='.$valor);
    $_ENV[$nombre] = $_SERVER[$nombre] = $valor;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
EntornoMySql::conectar();
$identidad = DB::selectOne('SELECT @@server_uuid uuid, @@port puerto, @@datadir datadir, DATABASE() base, VERSION() version');
$normalizar = fn (string $ruta): string => strtolower(rtrim(str_replace('\\', '/', $ruta), '/'));
if ($identidad->uuid !== $manifiesto['uuid'] || (int) $identidad->puerto !== (int) $manifiesto['puerto']
    || $identidad->base !== $manifiesto['base_visual'] || ! str_starts_with($identidad->version, '8.4.3')
    || $normalizar($identidad->datadir) !== $normalizar($manifiesto['datadir'])) {
    throw new RuntimeException('Servidor aislado no coincide: operación bloqueada antes de escribir.');
}
if ($administrar && DB::selectOne("SELECT COUNT(*) n FROM information_schema.schemata WHERE schema_name IN ('software_gestion_web_microempresa', 'ecolimp_respaldo_prueba')")->n > 0) {
    throw new RuntimeException('El servidor contiene una base protegida.');
}
config(['app.url' => 'http://127.0.0.1:8026', 'session.driver' => 'file', 'session.files' => $directorio.'/sesiones',
    'session.cookie' => 'dhacer_pr44_aislado', 'session.secure' => false, 'logging.default' => 'single',
    'logging.channels.single.path' => $directorio.'/evidencia/laravel.log']);

return compact('app', 'directorio', 'manifiesto', 'privadas', 'identidad');
