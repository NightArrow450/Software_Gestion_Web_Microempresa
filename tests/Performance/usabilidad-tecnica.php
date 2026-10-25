<?php

use App\Models\ConfiguracionEnvasado;
use App\Services\EntornoDemostracion;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Performance\AutorizacionRendimiento;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$m = (new AutorizacionRendimiento)->verificar('medir');
app(EntornoDemostracion::class)->verificarDatosSinteticos();
$base = $m['url'];
$http = Http::withoutRedirecting()->timeout(60)->withOptions(['proxy' => '', 'cookies' => new CookieJar]);
$ocultos = static function (string $html): array {
    preg_match_all('/<input\b[^>]*>/i', $html, $entradas);
    $datos = [];
    foreach ($entradas[0] as $entrada) {
        if (preg_match('/name="([^"]+)"/', $entrada, $nombre) && preg_match('/value="([^"]*)"/', $entrada, $valor)) {
            $datos[$nombre[1]] = html_entity_decode($valor[1], ENT_QUOTES);
        }
    }

    return $datos;
};
$login = $http->get($base.'/login');
$r = $http->asForm()->post($base.'/login', ['_token' => $ocultos($login->body())['_token'], 'email' => 'admin@carga.invalid', 'password' => getenv('CARGA_PASSWORD')]);
if ($r->status() !== 302 || ! str_ends_with((string) $r->header('Location'), '/dashboard')) {
    throw new RuntimeException('Autenticación ficticia no verificada.');
}
$form = $http->get($base.'/pedidos/nuevo');
if ($form->status() !== 200) {
    throw new RuntimeException('Formulario de pedido no disponible.');
}
$pedidosAntes = DB::table('pedidos')->count();
$inicio = hrtime(true);
$datos = array_intersect_key($ocultos($form->body()), array_flip(['_token', '_token_operacion']));
$datos += ['cliente_id' => DB::table('clientes')->orderBy('id')->value('id'), 'fecha_pedido' => date('Y-m-d'), 'fecha_entrega' => date('Y-m-d'), 'condicion_pago' => 'contado', 'observaciones' => 'Medición técnica HTTP de cinco líneas, datos ficticios.'];
$datos['detalles'] = DB::table('presentaciones_producto')->orderBy('id')->limit(5)->pluck('id')->map(static fn ($id): array => ['tipo_linea' => 'normal', 'presentacion_producto_id' => $id, 'modalidad' => 'unidad', 'cantidad' => 1])->all();
$r = $http->asForm()->post($base.'/pedidos', $datos);
$destino = (string) $r->header('Location');
if ($r->status() !== 302 || ! preg_match('~\A'.preg_quote($base, '~').'/pedidos/([0-9]+)\z~', $destino, $id) || $http->get($destino)->status() !== 200
    || DB::table('pedido_detalles')->where('pedido_id', $id[1])->count() !== 5 || DB::table('pedidos')->count() !== $pedidosAntes + 1) {
    throw new RuntimeException('Pedido de cinco líneas no confirmado; no reintentar automáticamente.');
}
$pedido = ['segundos' => (hrtime(true) - $inicio) / 1e9, 'pedido_id' => (int) $id[1], 'lineas' => 5, 'tipo' => 'Automatización HTTP, no trabajador ni medición de tecleo'];
$config = ConfiguracionEnvasado::with('materiales')->orderBy('id')->firstOrFail();
$materialesAntes = DB::table('insumos')->whereIn('id', $config->materiales->pluck('insumo_id'))->pluck('cantidad_actual', 'id')->all();
$stockAntes = (int) DB::table('inventarios')->where('presentacion_producto_id', $config->presentacion_producto_id)->value('cantidad_actual');
$envasadosAntes = DB::table('envasados')->count();
$movimientosAntes = DB::table('movimientos_inventario')->count();
$form = $http->get($base.'/envasados/nuevo');
if ($form->status() !== 200) {
    throw new RuntimeException('Formulario de envasado no disponible.');
}
$inicio = hrtime(true);
$datos = ['_token' => $ocultos($form->body())['_token'], 'presentacion_producto_id' => $config->presentacion_producto_id,
    'configuracion_envasado_id' => $config->id, 'version_configuracion' => $config->version, 'cantidad' => 2, 'observaciones' => 'Medición técnica HTTP ficticia y prueba de doble envío.'];
$previa = $http->asForm()->post($base.'/envasados/previsualizar', $datos);
if ($previa->status() !== 200) {
    throw new RuntimeException('Previsualización de envasado fallida.');
}
$confirmacion = $ocultos($previa->body());
$r = $http->asForm()->post($base.'/envasados', $confirmacion);
$destino = (string) $r->header('Location');
if ($r->status() !== 302 || ! preg_match('~\A'.preg_quote($base, '~').'/envasados/[0-9]+\z~', $destino) || $http->get($destino)->status() !== 200) {
    throw new RuntimeException('Envasado no confirmado; no repetir automáticamente.');
}
$duracion = (hrtime(true) - $inicio) / 1e9;
$repetido = $http->asForm()->post($base.'/envasados', $confirmacion);
$stockDespues = (int) DB::table('inventarios')->where('presentacion_producto_id', $config->presentacion_producto_id)->value('cantidad_actual');
$materialesDespues = DB::table('insumos')->whereIn('id', $config->materiales->pluck('insumo_id'))->pluck('cantidad_actual', 'id')->all();
$consumos = [];
foreach ($materialesAntes as $id => $cantidad) {
    $consumos[$id] = (float) $cantidad - (float) $materialesDespues[$id];
}
$correcto = $stockDespues === $stockAntes + 2 && DB::table('envasados')->count() === $envasadosAntes + 1
    && DB::table('movimientos_inventario')->count() === $movimientosAntes + 4 && count($consumos) === 3
    && count(array_filter($consumos, static fn (float $n): bool => $n !== 2.0)) === 0
    && $repetido->status() === 302 && $repetido->header('Location') === $destino;
echo json_encode(['fecha' => date(DATE_ATOM), 'pedido_cinco_lineas' => $pedido, 'envasado' => ['segundos' => $duracion, 'tipo' => 'Automatización HTTP', 'stock_antes' => $stockAntes,
    'stock_despues' => $stockDespues, 'consumos' => $consumos, 'movimientos_nuevos' => DB::table('movimientos_inventario')->count() - $movimientosAntes,
    'doble_envio_controlado' => $correcto]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
if (! $correcto) {
    throw new RuntimeException('Envasado observado: conservar evidencia y no reparar.');
}
