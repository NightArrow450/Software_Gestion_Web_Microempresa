<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class EntornoDemostracion
{
    /** @return array{base: string, puerto: int} */
    public function verificar(): array
    {
        if (! config('demostracion.activa') || ! app()->environment('demo')) {
            throw new RuntimeException('Se requiere un entorno de demostración explícito.');
        }
        $operativo = realpath((string) config('demostracion.proyecto_operativo'));
        if ($operativo === false || strcasecmp($operativo, (string) realpath(base_path())) === 0) {
            throw new RuntimeException('Se requiere un checkout separado y la ruta operativa verificable.');
        }
        $nombre = config('database.default');
        $c = config('database.connections.'.$nombre, []);
        $base = (string) ($c['database'] ?? '');
        $puerto = (int) ($c['port'] ?? 0);
        $usuario = (string) ($c['username'] ?? '');
        if (($c['driver'] ?? '') !== 'mysql' || ! empty($c['url']) || ! empty($c['unix_socket'])
            || ! empty($c['read']) || ! empty($c['write'])
            || ($c['host'] ?? '') !== '127.0.0.1'
            || $puerto < 1024 || $puerto === 3306 || $puerto !== (int) config('demostracion.puerto')
            || ! preg_match('/\Adhacer_eco_limp_demo_[a-z0-9_]{1,32}\z/', $base)
            || ! preg_match('/\Ademo_[a-z0-9_]+\z/', $usuario)
            || empty($c['password'])
            || ! preg_match('/\A[a-f0-9-]{36}\z/i', (string) config('demostracion.servidor_uuid'))) {
            throw new RuntimeException('Conexión de demostración no permitida. Revisa servidor, puerto, base y credenciales limitadas.');
        }

        $conexion = DB::connection();
        $identidad = $conexion->selectOne('SELECT DATABASE() AS base, @@port AS puerto, @@server_uuid AS uuid, CURRENT_USER() AS cuenta, VERSION() AS version, @@partial_revokes AS revocaciones_parciales');
        if ($identidad === null || $identidad->base !== $base || (int) $identidad->puerto !== $puerto
            || $identidad->uuid !== config('demostracion.servidor_uuid')
            || ! str_starts_with($identidad->cuenta, $usuario.'@')
            || ! str_starts_with($identidad->version, '8.4.')) {
            throw new RuntimeException('La identidad efectiva del servidor no corresponde a la demostración.');
        }
        $permisos = $conexion->select('SHOW GRANTS FOR CURRENT_USER');
        if ($permisos === []) {
            throw new RuntimeException('No se pudieron verificar los privilegios de demostración.');
        }
        $tieneAcceso = false;
        $baseConcedida = (bool) $identidad->revocaciones_parciales ? $base : str_replace('_', '\\_', $base);
        foreach ($permisos as $fila) {
            $concesion = (string) array_values(get_object_vars($fila))[0];
            if (preg_match('/\AGRANT USAGE ON \*\.\* TO /', $concesion) && ! str_contains($concesion, 'WITH GRANT OPTION')) {
                continue;
            }
            if (! preg_match('/\AGRANT (.+) ON `'.preg_quote($baseConcedida, '/').'`\.\* TO /', $concesion)
                || str_contains($concesion, 'WITH GRANT OPTION')) {
                throw new RuntimeException('La cuenta tiene privilegios fuera de su base o delegación no permitida.');
            }
            $tieneAcceso = true;
        }
        if (! $tieneAcceso) {
            throw new RuntimeException('La cuenta no tiene acceso a su base de demostración.');
        }

        return ['base' => $base, 'puerto' => $puerto];
    }

    public function verificarDatosSinteticos(): void
    {
        $this->verificar();
        if (config('demostracion.datos_sinteticos_confirmados') !== true) {
            throw new RuntimeException('Falta la confirmación de procedencia exclusivamente sintética de los datos.');
        }
    }
}
