<?php

namespace Tests\Performance;

use App\Services\EntornoDemostracion;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Herramienta de desarrollo: nunca se registra como middleware o ruta operativa. */
final class AutorizacionRendimiento
{
    public const PROTEGIDAS = ['software_gestion_web_microempresa', 'ecolimp_respaldo_prueba'];

    public const CUPOS_INSPECCION = 8;

    /** @return array<string, mixed> */
    public function verificar(string $operacion): array
    {
        $ruta = realpath(self::variablePrivada('RENDIMIENTO_MANIFIESTO'));
        $hash = self::variablePrivada('RENDIMIENTO_MANIFIESTO_SHA256');
        if (! $ruta || ! preg_match('/\A[a-f0-9]{64}\z/', $hash)
            || str_starts_with(strtolower(str_replace('\\', '/', $ruta)), strtolower(str_replace('\\', '/', base_path())).'/')
            || ! hash_equals($hash, hash_file('sha256', $ruta))) {
            throw new RuntimeException('Falta un manifiesto privado externo con SHA-256 autorizado.');
        }
        $m = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($m)) {
            throw new RuntimeException('Manifiesto no válido.');
        }
        foreach (['checkout', 'operativo', 'mysql_datadir', 'mysql_binario', 'apache_binario', 'apache_config'] as $campo) {
            self::rutaCanonica((string) ($m[$campo] ?? ''));
        }
        self::validarConfiguracion($m, $operacion, [
            'checkout' => realpath(base_path()), 'operativo' => realpath((string) config('demostracion.proyecto_operativo')),
            'base' => config('database.connections.mysql.database'), 'puerto' => (int) config('database.connections.mysql.port'),
            'usuario' => config('database.connections.mysql.username'), 'host' => config('database.connections.mysql.host'),
            'app_url' => config('app.url'),
        ]);
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('Este procedimiento requiere el verificador de procesos Windows aprobado.');
        }
        $proceso = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-File', __DIR__.'/identidad-procesos.ps1',
            '-PidMySql', (string) $m['mysql_pid'], '-PidApache', (string) $m['apache_pid'],
            '-PuertoMySql', (string) $m['mysql_puerto'], '-PuertoApache', (string) parse_url($m['url'], PHP_URL_PORT), '-Manifiesto', $ruta]);
        $procesos = self::conCupoInspeccion(dirname($ruta), static function () use ($proceso): array {
            $proceso->setTimeout(15)->mustRun();

            return json_decode($proceso->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        });
        foreach (['mysql', 'apache'] as $tipo) {
            if (strcasecmp((string) realpath($m[$tipo.'_binario']), (string) realpath($procesos[$tipo]['binario'])) !== 0
                || ! hash_equals($m[$tipo.'_argumentos_sha256'], $procesos[$tipo]['argumentos_sha256'])) {
                throw new RuntimeException('El proceso efectivo no coincide con el manifiesto autorizado.');
            }
        }
        if (! hash_equals($m['apache_config_sha256'], hash_file('sha256', $m['apache_config']))) {
            throw new RuntimeException('La configuración Apache cambió después de autorizarla.');
        }
        app(EntornoDemostracion::class)->verificar();
        $identidad = DB::selectOne('SELECT DATABASE() AS base, VERSION() AS version, @@port AS puerto, @@datadir AS directorio, @@server_uuid AS uuid, CURRENT_USER() AS cuenta, CURRENT_ROLE() AS roles');
        self::validarIdentidad($m, (array) $identidad);

        return $m;
    }

    /** Serializa el coste del instrumento, sin reutilizar resultados ni omitir una inspección. */
    public static function conCupoInspeccion(string $directorio, callable $inspeccionar, float $esperaMaxima = 60.0): mixed
    {
        self::rutaCanonica($directorio);
        if ($esperaMaxima <= 0 || $esperaMaxima > 60) {
            throw new RuntimeException('Plazo de inspección no autorizado.');
        }
        $limite = microtime(true) + $esperaMaxima;
        $turno = $directorio.'/inspector-espera-'.sprintf('%020d', hrtime(true)).'-'.bin2hex(random_bytes(8)).'.turno';
        $solicitud = fopen($turno, 'x');
        if ($solicitud === false) {
            throw new RuntimeException('No se pudo registrar el turno de inspección.');
        }
        fclose($solicitud);
        try {
            do {
                $primeros = array_slice(glob($directorio.'/inspector-espera-*.turno') ?: [], 0, self::CUPOS_INSPECCION);
                if (! in_array($turno, $primeros, true)) {
                    usleep(25000);

                    continue;
                }
                for ($cupo = 0; $cupo < self::CUPOS_INSPECCION; $cupo++) {
                    $ruta = $directorio.'/inspector-'.$cupo.'.lock';
                    if (is_link($ruta)) {
                        throw new RuntimeException('Archivo de inspección redirigido.');
                    }
                    $archivo = fopen($ruta, 'c');
                    if ($archivo === false) {
                        throw new RuntimeException('No se pudo abrir el control de inspección.');
                    }
                    try {
                        if (flock($archivo, LOCK_EX | LOCK_NB)) {
                            try {
                                unlink($turno);
                                $turno = null;

                                return $inspeccionar();
                            } finally {
                                flock($archivo, LOCK_UN);
                            }
                        }
                    } finally {
                        fclose($archivo);
                    }
                }
                usleep(25000);
            } while (microtime(true) < $limite);

            throw new RuntimeException('Tiempo de espera agotado: no se omitirá la inspección de identidad.');
        } finally {
            if ($turno !== null && is_file($turno)) {
                unlink($turno);
            }
        }
    }

    /** @param array<string, mixed> $m
     * @param  array<string, mixed>  $c
     */
    public static function validarConfiguracion(array $m, string $operacion, array $c): void
    {
        foreach (['autorizacion', 'vence', 'checkout', 'operativo', 'base', 'usuario', 'url', 'mysql_datadir', 'mysql_uuid', 'mysql_binario', 'apache_binario', 'apache_config'] as $campo) {
            if (! is_string($m[$campo] ?? null) || trim($m[$campo]) === '') {
                throw new RuntimeException('Campo obligatorio ausente en el manifiesto: '.$campo);
            }
        }
        foreach (['mysql_argumentos_sha256', 'apache_argumentos_sha256', 'apache_config_sha256'] as $campo) {
            if (! preg_match('/\A[a-f0-9]{64}\z/', $m[$campo] ?? '')) {
                throw new RuntimeException('Huella de configuración o proceso no válida.');
            }
        }
        $puerto = $m['mysql_puerto'] ?? 0;
        $url = parse_url($m['url']);
        if (! is_int($puerto) || $puerto < 1024 || $puerto > 65535 || $puerto === 3306
            || ! is_int($m['mysql_pid'] ?? null) || $m['mysql_pid'] < 1 || ! is_int($m['apache_pid'] ?? null) || $m['apache_pid'] < 1
            || ! preg_match('/\Adhacer_eco_limp_demo_f9b_[a-z0-9_]{1,20}\z/', $m['base'])
            || in_array(strtolower($m['base']), self::PROTEGIDAS, true)
            || ! preg_match('/\Ademo_f9b_[a-z0-9_]{1,20}\z/', $m['usuario'])
            || ! is_array($url) || ($url['host'] ?? '') !== '127.0.0.1' || ! in_array($url['scheme'] ?? '', ['http', 'https'], true)
            || ! in_array($url['port'] ?? 0, [8089, 8449], true) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || ($url['path'] ?? '') !== '' || ! is_array($m['operaciones'] ?? null) || ! in_array($operacion, $m['operaciones'], true)
            || strtotime($m['vence']) === false || strtotime($m['vence']) <= time()
            || $c['host'] !== '127.0.0.1' || $c['base'] !== $m['base'] || $c['usuario'] !== $m['usuario'] || $c['puerto'] !== $puerto
            || $c['app_url'] !== $m['url'] || ! $c['checkout'] || ! $c['operativo']
            || self::normalizarRuta($c['checkout']) === self::normalizarRuta($c['operativo'])
            || self::normalizarRuta($c['checkout']) !== self::normalizarRuta($m['checkout']) || self::normalizarRuta($c['operativo']) !== self::normalizarRuta($m['operativo'])) {
            throw new RuntimeException('Destino, operación o identidad de configuración no autorizados.');
        }
    }

    public static function rutaCanonica(string $ruta): string
    {
        $real = realpath($ruta);
        if ($real === false || self::normalizarRuta($real) !== self::normalizarRuta($ruta)) {
            throw new RuntimeException('Ruta inexistente, no canónica o redirigida.');
        }
        for ($actual = $real; ; $actual = dirname($actual)) {
            if (is_link($actual)) {
                throw new RuntimeException('No se permiten enlaces en las rutas autorizadas.');
            }
            if (dirname($actual) === $actual) {
                break;
            }
        }

        return self::normalizarRuta($real);
    }

    /** @param array<int, array<string, mixed>> $filas */
    public static function validarPermisosIniciales(array $filas): void
    {
        if (count($filas) !== 1 || ($filas[0]['nombre'] ?? null) !== 'pedidos.cancelar'
            || ($filas[0]['descripcion'] ?? null) !== 'Permite cancelar pedidos con motivo.'
            || ! array_key_exists('es_sistema', $filas[0]) || (bool) $filas[0]['es_sistema'] !== false) {
            throw new RuntimeException('Carga bloqueada: permisos distintos del registro técnico de la migración inicial.');
        }
    }

    private static function normalizarRuta(string $ruta): string
    {
        $ruta = rtrim(str_replace('\\', '/', $ruta), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($ruta) : $ruta;
    }

    public static function variablePrivada(string $nombre): string
    {
        return (string) ($_ENV[$nombre] ?? getenv($nombre));
    }

    /** @param array<string, mixed> $m
     * @param  array<string, mixed>  $i
     */
    public static function validarIdentidad(array $m, array $i): void
    {
        $normalizar = static fn (string $s): string => strtolower(rtrim(str_replace('\\', '/', $s), '/'));
        if (($i['base'] ?? null) !== $m['base'] || (int) ($i['puerto'] ?? 0) !== $m['mysql_puerto']
            || ($i['uuid'] ?? null) !== $m['mysql_uuid'] || ! str_starts_with($i['version'] ?? '', '8.4.3')
            || ! str_starts_with($i['cuenta'] ?? '', $m['usuario'].'@') || ($i['roles'] ?? null) !== 'NONE'
            || $normalizar($i['directorio'] ?? '') !== $normalizar($m['mysql_datadir'])) {
            throw new RuntimeException('La identidad efectiva MySQL no corresponde al entorno autorizado.');
        }
    }
}
