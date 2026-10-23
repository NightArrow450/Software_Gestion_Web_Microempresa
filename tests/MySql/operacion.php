<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\MySql\EntornoMySql;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$datos = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$request = Request::create($datos['ruta'], $datos['metodo'] ?? 'POST', $datos['datos']);
$app->instance('request', $request);
$kernel->bootstrap();
EntornoMySql::conectar();
$usuario = User::findOrFail($datos['usuario_id']);
Auth::setUser($usuario);
$conexion = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
fwrite(STDOUT, 'LISTO:'.$conexion.PHP_EOL);
fflush(STDOUT);
$request->headers->set('Accept', 'application/json');
$response = $kernel->handle($request);
fwrite(STDOUT, json_encode(['estado' => $response->getStatusCode(), 'contenido' => $response->getContent()], JSON_THROW_ON_ERROR).PHP_EOL);
$kernel->terminate($request, $response);
