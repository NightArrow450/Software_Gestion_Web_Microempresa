<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigirCambioPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->debe_cambiar_password && ! $request->routeIs('password.cambiar', 'password.actualizar', 'logout')) {
            return redirect()->route('password.cambiar');
        }

        return $next($request);
    }
}
