<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CambioPasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.cambiar-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $regla = Password::min(10)->letters()->mixedCase()->numbers();
        if (app()->environment('production')) {
            $regla->uncompromised();
        }
        $datos = $request->validate([
            'password_actual' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:password_actual', $regla],
        ]);
        $request->user()->forceFill([
            'password' => $datos['password'], 'debe_cambiar_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada correctamente.');
    }
}
