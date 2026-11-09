<?php

require __DIR__.'/entorno.php';

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publico = realpath(dirname(__DIR__, 2).'/public');
$archivo = realpath($publico.'/'.$ruta);
if ($ruta !== '/' && $archivo && str_starts_with($archivo, $publico.'/') && is_file($archivo)) {
    return false;
}
require dirname(__DIR__, 2).'/public/index.php';
