<?php

use Illuminate\Contracts\Console\Kernel;
use Tests\Performance\AutorizacionRendimiento;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $clave = (string) getenv('RENDIMIENTO_CLAVE_CONTROL');
    $nonce = (string) ($_SERVER['HTTP_X_CONTROL_NONCE'] ?? '');
    $operacion = (string) ($_SERVER['HTTP_X_CONTROL_OPERACION'] ?? '');
    if (! in_array($operacion, ['medir', 'seguridad'], true) || strlen($clave) < 32 || ! preg_match('/\A[a-f0-9]{32,64}\z/', $nonce)
        || strcasecmp((string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''), (string) realpath($app->publicPath())) !== 0) {
        throw new RuntimeException('Control no autorizado.');
    }
    $m = (new AutorizacionRendimiento)->verificar($operacion);
    $mensaje = $m['autorizacion'].'|'.$m['url'].'|'.$m['base'].'|'.$operacion.'|'.$nonce;
    echo json_encode(['autorizacion' => $m['autorizacion'], 'url' => $m['url'], 'base' => $m['base'], 'firma' => hash_hmac('sha256', $mensaje, $clave)], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(503);
    echo '{"mensaje":"Entorno de rendimiento no verificado."}';
}
