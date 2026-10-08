<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermisoMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$permisos
    ): Response {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->status) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Tu cuenta se encuentra inactiva.',
                ]);
        }

        $user->loadMissing('role.permisos');

        if (!$user->role) {
            abort(403);
        }

        foreach ($permisos as $permiso) {
            $tienePermiso = $user->role->permisos
                ->contains('nombre', $permiso);

            if (!$tienePermiso) {
                abort(403);
            }
        }

        return $next($request);
    }
}
