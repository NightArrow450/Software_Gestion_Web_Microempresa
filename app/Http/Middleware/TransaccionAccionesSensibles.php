<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TransaccionAccionesSensibles
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->user() || ! $request->routeIs('usuarios.*', 'roles.*', 'permisos.*')) {
            return $next($request);
        }

        try {
            return DB::transaction(function () use ($request, $next): Response {
                $respuesta = $next($request);
                if (($respuesta->exception ?? null) !== null || $respuesta->getStatusCode() >= 400) {
                    throw new HttpResponseException($respuesta);
                }

                return $respuesta;
            }, 3);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        }
    }
}
