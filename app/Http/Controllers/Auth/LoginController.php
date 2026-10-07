<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            'email.required' =>
                'El correo electrónico es obligatorio.',

            'email.email' =>
                'Ingresa un correo electrónico válido.',

            'password.required' =>
                'La contraseña es obligatoria.',
        ]);

        $usuario = User::where(
            'email',
            $credenciales['email']
        )->first();

        if (
            !$usuario ||
            !Hash::check(
                $credenciales['password'],
                $usuario->password
            )
        ) {
            return back()
                ->withErrors([
                    'email' =>
                        'Correo electrónico o contraseña incorrectos.',
                ])
                ->onlyInput('email');
        }

        if (!$usuario->status) {
            return back()
                ->withErrors([
                    'email' =>
                        'Tu cuenta se encuentra inactiva. Comunícate con el administrador.',
                ])
                ->onlyInput('email');
        }

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