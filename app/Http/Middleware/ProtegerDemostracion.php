<?php

namespace App\Http\Middleware;

use App\Services\EntornoDemostracion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProtegerDemostracion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('demostracion.activa') || app()->environment('demo')) {
            try {
                app(EntornoDemostracion::class)->verificarDatosSinteticos();
            } catch (Throwable) {
                return response('Demostración bloqueada: no se verificó el entorno independiente y la procedencia sintética de los datos.', 503);
            }
            $request->attributes->set('demostracion_verificada', true);
        }

        return $next($request);
    }
}
