<?php

use Illuminate\Contracts\Console\Kernel;
use Tests\Performance\AutorizacionRendimiento;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    $m = (new AutorizacionRendimiento)->verificar($argv[1] ?? 'verificar');
    echo json_encode(['estado' => 'VERIFICADO', 'autorizacion' => $m['autorizacion'], 'base' => $m['base'], 'url' => $m['url']], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'BLOQUEADO: revisar manifiesto, procesos, configuración e identidad; no se inicializó ningún dato.'.PHP_EOL);
    exit(1);
}
