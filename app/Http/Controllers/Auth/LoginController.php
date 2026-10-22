<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credenciales = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',

            'email.email' => 'Ingresa un correo electrónico válido.',

            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $credenciales['email'] = mb_strtolower(trim($credenciales['email']));
        $clave = 'login:'.hash('sha256', $credenciales['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $segundos = RateLimiter::availableIn($clave);
            Log::channel('seguridad')->warning('login_bloqueado', ['email' => $credenciales['email'], 'ip' => $request->ip()]);

            return back()->withErrors(['email' => 'Demasiados intentos fallidos. Intenta nuevamente en '.$segundos.' segundos.'])
                ->onlyInput('email')->header('Retry-After', (string) $segundos);
        }

        $usuario = User::whereRaw(
            'LOWER(email) = ?',
            [mb_strtolower($credenciales['email'])]
        )->first();

        if (
            ! $usuario ||
            ! Hash::check(
                $credenciales['password'],
                $usuario->password
            )
        ) {
            RateLimiter::hit($clave, 60);
            Log::channel('seguridad')->warning('login_fallido', ['email' => $credenciales['email'], 'ip' => $request->ip()]);

            return back()
                ->withErrors([
                    'email' => 'Correo electrónico o contraseña incorrectos.',
                ])
                ->onlyInput('email');
        }

        if (! $usuario->status) {
            RateLimiter::hit($clave, 60);
            Log::channel('seguridad')->warning('login_fallido', ['email' => $credenciales['email'], 'ip' => $request->ip(), 'motivo' => 'cuenta_inactiva']);

            return back()
                ->withErrors([
                    'email' => 'Tu cuenta se encuentra inactiva. Comunícate con el administrador.',
                ])
                ->onlyInput('email');
        }

        RateLimiter::clear($clave);

        Auth::login(
            $usuario,
            $request->boolean('remember')
        );

        $request->session()->regenerate();

        return redirect()
            ->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with(
                'success',
                'Sesión cerrada correctamente.'
            );
    }
}
