<?php

/** Solo con operación seguridad autorizada; NO ejecutar durante la preparación. */
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Tests\Performance\AutorizacionRendimiento;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    $m = (new AutorizacionRendimiento)->verificar('seguridad');
    $clave = (string) getenv('RENDIMIENTO_CLAVE_CONTROL');
    $pedido = (string) getenv('PEDIDO_FICTICIO_ID');
    if (strlen($clave) < 32 || ! preg_match('/\A[1-9][0-9]*\z/', $pedido)) {
        throw new RuntimeException('Falta autorización privada.');
    }
    $nonce = bin2hex(random_bytes(24));
    $control = Http::withoutRedirecting()->withOptions(['proxy' => ''])->withHeaders(['X-Control-Nonce' => $nonce, 'X-Control-Operacion' => 'seguridad'])->get($m['url'].'/__control_rendimiento');
    $firma = hash_hmac('sha256', $m['autorizacion'].'|'.$m['url'].'|'.$m['base'].'|seguridad|'.$nonce, $clave);
    if ($control->status() !== 200 || ! hash_equals($firma, (string) $control->json('firma'))) {
        throw new RuntimeException('El HTTP efectivo no acredita el entorno autorizado.');
    }
    $cuentas = json_decode(file_get_contents((string) getenv('RENDIMIENTO_CUENTAS')), true, 512, JSON_THROW_ON_ERROR);
    $cuenta = $cuentas[0] ?? [];
    if (! preg_match('/\Avu[1-9][0-9]*@carga\.invalid\z/', $cuenta['email'] ?? '')) {
        throw new RuntimeException('Cuenta ficticia no válida.');
    }
    $cliente = Http::withoutRedirecting()->timeout(30)->withOptions(['proxy' => ['http' => 'http://127.0.0.1:8090', 'https' => 'http://127.0.0.1:8090', 'no' => []], 'cookies' => new CookieJar]);
    $login = $cliente->get($m['url'].'/login');
    if ($login->status() !== 200 || ! preg_match('/name="_token"\s+value="([^"]+)"/', $login->body(), $coincidencia)) {
        throw new RuntimeException('Formulario CSRF no válido.');
    }
    $respuesta = $cliente->asForm()->post($m['url'].'/login', ['_token' => html_entity_decode($coincidencia[1]), 'email' => $cuenta['email'], 'password' => $cuenta['password']]);
    if ($respuesta->status() !== 302 || ! str_ends_with((string) $respuesta->header('Location'), '/dashboard')) {
        throw new RuntimeException('No se obtuvo una sesión válida.');
    }
    foreach (['/dashboard', '/pedidos', '/productos?search=sint%C3%A9tico', '/pedidos/'.$pedido, '/cumplimiento/lista-preparacion', '/envasados'] as $ruta) {
        $respuesta = $cliente->get($m['url'].$ruta);
        if ($respuesta->status() !== 200 || str_contains($respuesta->body(), 'name="password"')) {
            throw new RuntimeException('Recorrido incompleto.');
        }
    }
    echo 'Recorrido pasivo completado: login y seis consultas, sin seguir enlaces externos ni enviar formularios comerciales.'.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'BLOQUEADO U OBSERVADO: no se completó el recorrido; conservar evidencia privada sin credenciales.'.PHP_EOL);
    exit(1);
}
