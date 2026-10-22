<?php

use App\Http\Middleware\ExigirCambioPassword;
use App\Http\Middleware\PermisoMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ExigirCambioPassword::class]);

        $middleware->alias([

            'role' => RoleMiddleware::class,

            'permiso' => PermisoMiddleware::class,

        ]);

    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password_actual']);

    })

    ->create();
