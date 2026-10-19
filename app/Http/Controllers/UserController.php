<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Listar usuarios
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = User::with('role');

        // Buscar por nombres, apellidos o correo
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtrar por rol
        if ($request->filled('role')) {
            $query->where('role_id', $request->role);
        }

        // Filtrar por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::orderBy('nombre')->get();

        return view(
            'users.index',
            compact('users', 'roles')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Formulario nuevo usuario
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $roles = Role::orderBy('nombre')->get();

        return view(
            'users.create',
            compact('roles')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Registrar usuario
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $this->normalizarCorreo($request);

        $validated = $request->validate([
            'first_name' => [
                'required',
                'string',
                'min:2',
                "regex:~\A[\p{L}\p{M}]+(?:[ '’\-][\p{L}\p{M}]+)*\z~u",
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'min:2',
                "regex:~\A[\p{L}\p{M}]+(?:[ '’\-][\p{L}\p{M}]+)*\z~u",
                'max:100',
            ],

            'email' => [
                'required',
                'bail',
                'email:rfc',
                'max:255',
                $this->correoUnico(),
            ],

            'password' => [
                'required',
                'string',
                $this->reglaContrasena(),
                'confirmed',
            ],

            'role_id' => [
                'required',
                'exists:roles,id',
            ],

            'status' => [
                'required',
                'boolean',
            ],
        ], [
            'first_name.required' => 'Los nombres son obligatorios.',

            'last_name.required' => 'Los apellidos son obligatorios.',

            'email.required' => 'El correo electrónico es obligatorio.',

            'email.email' => 'Ingresa un correo electrónico válido.',

            'email.unique' => 'Este correo electrónico ya está registrado.',

            'password.required' => 'La contraseña es obligatoria.',

            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',

            'password.confirmed' => 'Las contraseñas no coinciden.',

            'role_id.required' => 'Selecciona un rol.',

            'role_id.exists' => 'El rol seleccionado no es válido.',

            'status.required' => 'Selecciona un estado.',
        ]);

        User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $validated['role_id'],
            'status' => (bool) $validated['status'],
        ]);

        return redirect()
            ->route('usuarios.index')
            ->with(
                'success',
                'Usuario registrado correctamente.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Ver usuario
    |--------------------------------------------------------------------------
    */

    public function show(User $user)
    {
        $user->load('role');

        return view(
            'users.show',
            compact('user')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Formulario editar usuario
    |--------------------------------------------------------------------------
    */

    public function edit(User $user)
    {
        $roles = Role::orderBy('nombre')->get();

        return view(
            'users.edit',
            compact('user', 'roles')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Actualizar usuario
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        User $user
    ) {
        $this->normalizarCorreo($request);

        $validated = $request->validate([
            'first_name' => [
                'required',
                'string',
                'min:2',
                "regex:~\A[\p{L}\p{M}]+(?:[ '’\-][\p{L}\p{M}]+)*\z~u",
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'min:2',
                "regex:~\A[\p{L}\p{M}]+(?:[ '’\-][\p{L}\p{M}]+)*\z~u",
                'max:100',
            ],

            'email' => [
                'required',
                'bail',
                'email:rfc',
                'max:255',

                $this->correoUnico($user),
            ],

            'password' => [
                'nullable',
                'string',
                $this->reglaContrasena(),
                'confirmed',
            ],

            'role_id' => [
                'required',
                'exists:roles,id',
            ],

            'status' => [
                'required',
                'boolean',
            ],
        ]);

        $data = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
            'status' => (bool) $validated['status'],
        ];

        /*
        |--------------------------------------------------------------------------
        | Evitar autodesactivación
        |--------------------------------------------------------------------------
        */

        if (
            auth()->id() === $user->id &&
            ! $data['status']
        ) {
            return back()
                ->withErrors([
                    'status' => 'No puedes desactivar tu propia cuenta mientras estás utilizando el sistema.',
                ])
                ->withInput();
        }

        /*
        |--------------------------------------------------------------------------
        | Cambiar contraseña solo si se ingresó una nueva
        |--------------------------------------------------------------------------
        */

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        return redirect()
            ->route('usuarios.show', $user)
            ->with(
                'success',
                'Usuario actualizado correctamente.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Activar / desactivar usuario
    |--------------------------------------------------------------------------
    */

    private function normalizarCorreo(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }

    private function correoUnico(?User $user = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($user): void {
            $query = User::whereRaw('LOWER(email) = ?', [mb_strtolower($value)]);
            if ($user) {
                $query->where('id', '!=', $user->id);
            }
            if ($query->exists()) {
                $fail('Este correo electrónico ya está registrado.');
            }
        };
    }

    private function reglaContrasena(): Password
    {
        $regla = Password::min(10)->letters()->mixedCase()->numbers();

        if (app()->environment('production')) {
            $regla->uncompromised();
        }

        return $regla;
    }

    public function toggleStatus(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with(
                'error',
                'No puedes desactivar tu propia cuenta.'
            );
        }

        $user->update([
            'status' => ! $user->status,
        ]);

        $message = $user->status
            ? 'Usuario activado correctamente.'
            : 'Usuario desactivado correctamente.';

        return back()->with(
            'success',
            $message
        );
    }
}
