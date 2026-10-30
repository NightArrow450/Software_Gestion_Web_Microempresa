<?php

use Illuminate\Http\Request;

$entorno = require __DIR__.'/entorno_redisenio.php';
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:8026') {
    http_response_code(403);
    exit('Servidor reservado para la revisión local aislada.');
}
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === '/_pr44/identidad') {
    header('Content-Type: application/json');
    $nonce = (string) ($_GET['nonce'] ?? '');
    echo json_encode(['uuid' => $entorno['identidad']->uuid, 'base' => $entorno['identidad']->base,
        'firma' => hash_hmac('sha256', $nonce.'|'.$entorno['identidad']->uuid.'|'.$entorno['identidad']->base, $entorno['privadas']['visual'])], JSON_THROW_ON_ERROR);

    return;
}
$publico = realpath(dirname(__DIR__, 2).'/public');
$archivo = realpath($publico.'/'.$uri);
if ($archivo && str_starts_with($archivo, $publico.DIRECTORY_SEPARATOR) && is_file($archivo) && strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}
$_SERVER['SCRIPT_FILENAME'] = $publico.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$entorno['app']->handleRequest(Request::capture());
