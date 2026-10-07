<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $usuario = $request->user();

        if (!$usuario) {
            return redirect()->route('login');
        }

        if (!$usuario->status) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Tu cuenta se encuentra inactiva.',
                ]);
        }

        $nombreRol = $usuario->role?->nombre;

        if (
            !$nombreRol ||
            !in_array($nombreRol, $roles, true)
        ) {
            abort(403);
        }

        return $next($request);
    }
}