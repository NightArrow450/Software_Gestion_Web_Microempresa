<?php

use App\Services\EntornoDemostracion;
use App\Services\FilasIndicadores;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Performance\AutorizacionRendimiento;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$m = (new AutorizacionRendimiento)->verificar('seguridad');
app(EntornoDemostracion::class)->verificarDatosSinteticos();
$clave = (string) getenv('CARGA_PASSWORD');
if (strlen($clave) < 10 || DB::table('pedidos')->count() < 3000) {
    throw new RuntimeException('Faltan el dataset y las credenciales sintéticas verificadas.');
}
$base = $m['url'];
$resultados = [];
$registrar = static function (string $caso, bool $correcto, array $detalle = []) use (&$resultados): void {
    $resultados[] = ['caso' => $caso, 'resultado' => $correcto ? 'APROBADO' : 'OBSERVADO', 'detalle' => $detalle];
};
$cliente = static fn () => Http::withoutRedirecting()->timeout(60)->withOptions(['proxy' => '', 'cookies' => new CookieJar]);
$token = static function (string $html): string {
    if (! preg_match('/(?:name="_token"\s+value|name="csrf-token"\s+content)="([^"]+)"/', $html, $coincidencia)) {
        throw new RuntimeException('No se encontró CSRF real.');
    }

    return html_entity_decode($coincidencia[1]);
};
$entrar = static function ($http, string $correo) use ($base, $token, $clave): void {
    $pagina = $http->get($base.'/login');
    $r = $http->asForm()->post($base.'/login', ['_token' => $token($pagina->body()), 'email' => $correo, 'password' => $clave]);
    if ($r->status() !== 302 || ! str_ends_with((string) $r->header('Location'), '/dashboard')) {
        throw new RuntimeException('No se logró autenticar la cuenta ficticia prevista.');
    }
};
$invitado = $cliente();
$registrar('invitado_ruta_protegida', $invitado->get($base.'/dashboard')->status() === 302);
$registrar('csrf_login_ausente', $invitado->asForm()->post($base.'/login', ['email' => 'vu1@carga.invalid', 'password' => 'incorrecta'])->status() === 419);
$admin = $cliente();
$entrar($admin, 'admin@carga.invalid');
$pagina = $admin->get($base.'/dashboard');
$registrar('administrador_dashboard', $pagina->status() === 200);
$sku = (int) DB::table('presentaciones_producto')->orderBy('id')->value('id');
foreach (['/usuarios', '/roles-permisos', '/clientes', '/productos', '/pedidos', '/pedidos/nuevo', '/pedidos/10', '/pedidos/imprimir-por-fecha?fecha_pedido='.date('Y-m-d'),
    '/cumplimiento/lista-preparacion', '/cumplimiento/10', '/inventario', '/inventario/movimientos', '/envasados', '/envasados/nuevo',
    '/presentaciones/'.$sku.'/materiales-envasado', '/modalidades-surtidas', '/seguimiento', '/indicadores', '/auditoria'] as $ruta) {
    $inicio = hrtime(true);
    $lectura = $admin->get($base.$ruta);
    $registrar('lectura_http_'.$ruta, $lectura->status() === 200, ['http' => $lectura->status(), 'milisegundos' => (hrtime(true) - $inicio) / 1e6, 'alcance' => 'HTTP sin navegador; no certifica responsive ni JavaScript']);
}
foreach (array_keys(FilasIndicadores::REPORTES) as $reporte) {
    $csv = $admin->get($base.'/indicadores/exportar', ['reporte' => $reporte]);
    $registrar('csv_http_'.$reporte, $csv->status() === 200 && str_starts_with($csv->body(), "\xEF\xBB\xBF")
        && mb_check_encoding($csv->body(), 'UTF-8') && str_contains((string) strtok($csv->body(), "\n"), ';'), ['http' => $csv->status(), 'bytes' => strlen($csv->body())]);
}
$registrar('cabeceras', $pagina->header('X-Content-Type-Options') === 'nosniff' && in_array($pagina->header('X-Frame-Options'), ['DENY', 'SAMEORIGIN'], true), [
    'nosniff' => $pagina->header('X-Content-Type-Options'), 'frame' => $pagina->header('X-Frame-Options'),
    'csp_report_only' => $pagina->hasHeader('Content-Security-Policy-Report-Only'),
]);
$cookies = implode('; ', $pagina->headers()['Set-Cookie'] ?? []);
$registrar('cookies_http_only_samesite', str_contains(strtolower($cookies), 'httponly') && str_contains(strtolower($cookies), 'samesite=lax'), ['secure_por_https' => 'NO EVALUADO: origen HTTP local']);
$comercial = $cliente();
$entrar($comercial, 'vu1@carga.invalid');
$produccion = $cliente();
$entrar($produccion, 'produccion@carga.invalid');
foreach (['Administrador' => $admin, 'Comercial' => $comercial, 'Produccion' => $produccion] as $rol => $http) {
    $esperado = $rol === 'Administrador' ? 200 : 403;
    $registrar('auditoria_'.$rol, $http->get($base.'/auditoria')->status() === $esperado);
    $registrar('dashboard_'.$rol, $http->get($base.'/dashboard')->status() === 200);
}
$antes = DB::table('envasados')->count();
$registrar('csrf_envasado_invalido', $admin->asForm()->post($base.'/envasados', ['_token' => 'invalido'])->status() === 419 && DB::table('envasados')->count() === $antes);
$especial = $comercial->get($base.'/productos', ['search' => "' OR 1=1 -- <script>alert(1)</script>"]);
$registrar('buscador_entrada_especial', $especial->status() === 200 && ! str_contains($especial->body(), '<script>alert(1)</script>'));
$pedido = DB::table('pedidos')->orderBy('id')->first();
$payload = '<img src=x onerror="alert(901)">';
try {
    DB::table('pedidos')->where('id', $pedido->id)->update(['observaciones' => $payload]);
    $r = $admin->get($base.'/pedidos/'.$pedido->id);
    $registrar('observacion_html_escapada', $r->status() === 200 && ! str_contains($r->body(), $payload) && str_contains($r->body(), '&lt;img'));
} finally {
    DB::table('pedidos')->where('id', $pedido->id)->update(['observaciones' => $pedido->observaciones]);
}
$usuario = DB::table('users')->where('email', 'vu50@carga.invalid')->first();
$sesionUno = $cliente();
$sesionDos = $cliente();
$entrar($sesionUno, $usuario->email);
$entrar($sesionDos, $usuario->email);
try {
    DB::table('users')->where('id', $usuario->id)->update(['status' => false]);
    $registrar('D9A01_sesion_previa', $sesionUno->get($base.'/dashboard')->status() === 302);
    $registrar('D9A01_segunda_sesion_json', $sesionDos->withHeaders(['Accept' => 'application/json'])->get($base.'/dashboard')->status() === 401);
    $registrar('D9A01_otro_modulo', $sesionUno->get($base.'/pedidos')->status() === 302);
    $inactivo = $cliente();
    $p = $inactivo->get($base.'/login');
    $inactivo->asForm()->post($base.'/login', ['_token' => $token($p->body()), 'email' => $usuario->email, 'password' => $clave]);
    $registrar('cuenta_inactiva_no_ingresa', $inactivo->get($base.'/dashboard')->status() === 302);
} finally {
    DB::table('users')->where('id', $usuario->id)->update(['status' => $usuario->status]);
}
$p = $comercial->get($base.'/dashboard');
$comercial->asForm()->post($base.'/logout', ['_token' => $token($p->body())]);
$registrar('logout_invalida_sesion', $comercial->get($base.'/dashboard')->status() === 302);
$limitado = $cliente();
$p = $limitado->get($base.'/login');
for ($n = 0; $n < 6; $n++) {
    $r = $limitado->asForm()->withHeaders(['Referer' => $base.'/login'])->post($base.'/login', ['_token' => $token($p->body()), 'email' => 'vu49@carga.invalid', 'password' => 'incorrecta_sintetica']);
}
$registrar('sexto_intento_limitado', (int) $r->header('Retry-After') > 0, ['http' => $r->status(), 'retry_after_presente' => (int) $r->header('Retry-After') > 0]);
echo json_encode(['fecha' => date(DATE_ATOM), 'origen' => $base, 'casos' => $resultados], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
