<?php

/** auto_prepend_file exclusivamente en el Apache/CLI del checkout independiente. */
require_once dirname(__DIR__, 3).'/vendor/autoload.php';
$ruta = realpath((string) getenv('RENDIMIENTO_CONFIG'));
if (! $ruta || str_starts_with(strtolower(str_replace('\\', '/', $ruta)), strtolower(str_replace('\\', '/', dirname(__DIR__, 3))).'/')) {
    throw new RuntimeException('Falta configuración privada externa del entorno independiente.');
}
Dotenv\Dotenv::createUnsafeImmutable(dirname($ruta), basename($ruta))->load();
