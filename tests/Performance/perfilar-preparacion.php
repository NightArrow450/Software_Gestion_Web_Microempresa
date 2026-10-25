<?php

use App\Http\Controllers\CumplimientoPedidoController;
use App\Models\User;
use App\Services\EntornoDemostracion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Performance\AutorizacionRendimiento;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
(new AutorizacionRendimiento)->verificar('medir');
app(EntornoDemostracion::class)->verificarDatosSinteticos();
$usuario = User::with('role.permisos')->where('email', 'admin@carga.invalid')->firstOrFail();
Auth::setUser($usuario);
$consultas = 0;
$tiempoSql = 0.0;
$patrones = [];
DB::listen(static function (QueryExecuted $consulta) use (&$consultas, &$tiempoSql, &$patrones): void {
    $consultas++;
    $tiempoSql += $consulta->time;
    $patron = preg_replace('/\b\d+\b/', '?', $consulta->sql) ?? $consulta->sql;
    $patrones[$patron] = ($patrones[$patron] ?? 0) + 1;
});
$resultados = [];
foreach (['surtidos_hoy' => 0, 'normales_dos_dias' => 2] as $escenario => $dias) {
    $fecha = now('America/Lima')->addDays($dias)->toDateString();
    $request = Request::create('/cumplimiento/lista-preparacion', 'GET', ['fecha_desde' => $fecha, 'fecha_hasta' => $fecha]);
    $request->setUserResolver(static fn (): User => $usuario);
    $app->instance('request', $request);
    $consultas = 0;
    $tiempoSql = 0.0;
    $patrones = [];
    $inicio = hrtime(true);
    $vista = app(CumplimientoPedidoController::class)->listaPreparacion($request);
    $datos = $vista->getData();
    arsort($patrones);
    $resultados[$escenario] = ['fecha_entrega' => $fecha, 'pedidos' => $datos['pedidos']->count(), 'filas' => $datos['filas']->count(),
        'consultas' => $consultas, 'tiempo_sql_ms' => $tiempoSql, 'tiempo_total_ms' => (hrtime(true) - $inicio) / 1e6,
        'memoria_pico_proceso_bytes' => memory_get_peak_usage(true), 'patrones_repetidos' => array_slice($patrones, 0, 5, true)];
}
echo json_encode(['fecha' => date(DATE_ATOM), 'alcance' => 'Diagnóstico CLI del controlador sin renderizado Blade, solo lectura; sin OPcache CLI ni bindings, no sustituye latencias HTTP ni compara totales de períodos diferentes.',
    'escenarios' => $resultados], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
