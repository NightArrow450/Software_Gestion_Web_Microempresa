<?php

use App\Http\Controllers\SaludController;
use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\EvitarDobleEnvio;
use App\Http\Middleware\EvitarEdicionObsoleta;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/salud', SaludController::class)->name('salud');
        },
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CabecerasSeguridad::class);
        $middleware->replace(TrustProxies::class, ProxiesConfiables::class);
        $middleware->append(ForzarHttps::class);
        $middleware->web(append: [ExigirCambioPassword::class, EvitarDobleEnvio::class, EvitarEdicionObsoleta::class]);

        $middleware->alias([

            'role' => RoleMiddleware::class,

            'permiso' => PermisoMiddleware::class,

        ]);

    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password_actual']);
        $exceptions->stopIgnoring(HttpException::class);
        $exceptions->report(function (Throwable $exception): bool {
            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                return false;
            }
            $request = app()->bound('request') ? request() : null;
            $guard = Auth::guard();
            Log::channel('seguridad')->error('error_servidor', [
                'usuario_id' => $guard->hasUser() ? $guard->user()?->id : null,
                'ruta' => $request?->route()?->getName() ?? $request?->route()?->uri(),
                'metodo' => $request?->method(),
                'excepcion' => $exception::class,
            ]);

            return false;
        });
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($response->getStatusCode() === 403) {
                RegistroSeguridad::registrar('acceso_denegado', $request);
            }

            $codigo = $response->getStatusCode();
            if (in_array($codigo, [403, 404, 419, 500, 503], true)) {
                if ($request->expectsJson()) {
                    $response->setContent(json_encode(['mensaje' => match ($codigo) {
                        403 => 'Acceso no autorizado.', 404 => 'Recurso no encontrado.',
                        419 => 'Sesión vencida.', 503 => 'Servicio no disponible.',
                        default => 'No pudimos completar la solicitud.',
                    }], JSON_UNESCAPED_UNICODE));
                    $response->headers->set('Content-Type', 'application/json');
                } else {
                    $response->setContent(view('errors.'.$codigo)->render());
                    $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
                }
            }

            return CabecerasSeguridad::aplicar($response);
        });

    })

    ->create();
