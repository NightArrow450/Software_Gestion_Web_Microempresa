<?php

use App\Http\Middleware\ExigirCambioPassword;
use App\Http\Middleware\ForzarHttps;
use App\Http\Middleware\PermisoMiddleware;
use App\Http\Middleware\ProxiesConfiables;
use App\Http\Middleware\RoleMiddleware;
use App\Services\RegistroSeguridad;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(TrustProxies::class, ProxiesConfiables::class);
        $middleware->append(ForzarHttps::class);
        $middleware->web(append: [ExigirCambioPassword::class]);

        $middleware->alias([

            'role' => RoleMiddleware::class,

            'permiso' => PermisoMiddleware::class,

        ]);

    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password_actual']);
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() === 403) {
                RegistroSeguridad::registrar('acceso_denegado', $request);
            }

            return $response;
        });

    })

    ->create();
