<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$host = (string) config('database.connections.mysql.host');
$name = (string) config('database.connections.mysql.database');

if (getenv('RAILWAY_PILOT') !== '1' || ! $app->environment('production') || config('app.debug')
    || ! config('app.key') || config('database.default') !== 'mysql'
    || ! preg_match('/\A[a-z0-9-]+\.railway\.internal\z/i', $host)
    || $name !== 'dhacer_piloto' || config('database.connections.mysql.url')) {
    throw new RuntimeException('Configuración del piloto Railway no válida.');
}

if (DB::selectOne('SELECT DATABASE() AS nombre')->nombre !== $name) {
    throw new RuntimeException('La conexión no apunta a la base del piloto.');
}

$status = $kernel->call('migrate', ['--force' => true, '--no-interaction' => true]);
echo $kernel->output();
exit($status);
