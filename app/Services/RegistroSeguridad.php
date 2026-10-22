<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class RegistroSeguridad
{
    /** @param array<string, mixed> $contexto */
    public static function registrar(string $evento, Request $request, array $contexto = []): void
    {
        Log::channel('seguridad')->info($evento, [
            'usuario_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'ruta' => $request->route()?->getName(),
            'metodo' => $request->method(),
        ] + Arr::only($contexto, [
            'usuario_afectado_id', 'rol_id', 'permiso_id', 'estado',
            'rol_anterior_id', 'permisos_anteriores', 'permisos_nuevos',
        ]));
    }
}
