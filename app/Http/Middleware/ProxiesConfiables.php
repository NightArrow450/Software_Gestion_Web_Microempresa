<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProxiesConfiables extends TrustProxies
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;

    protected function proxies(): array|string
    {
        $proxies = array_values(array_filter(array_map(trim(...), explode(',', (string) config('seguridad.proxies_confiables', '')))));
        if (in_array('*', $proxies, true)) {
            return app()->environment('production') ? '*' : [];
        }

        return $proxies;
    }

    public function handle(Request $request, Closure $next): Response
    {
        return parent::handle($request, function (Request $request) use ($next): Response {
            if (app()->environment('production') && config('seguridad.modo_proxy') === 'railway' && $request->isFromTrustedProxy()) {
                $ip = $request->header('X-Real-IP');
                $request->headers->remove('X-Forwarded-For');
                if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $request->headers->set('X-Forwarded-For', $ip);
                }
            }

            return $next($request);
        });
    }
}
