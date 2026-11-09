<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForzarHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! $request->isSecure() && ! $request->is('salud', 'up')) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
