<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('administrador.email');
        $password = config('administrador.password');
        if (! is_string($email) || trim($email) === '' || ! is_string($password) || $password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('Debes configurar ADMIN_EMAIL y ADMIN_PASSWORD antes de crear el administrador en producción.');
            }
            $this->command?->warn('No se creó el administrador: configura ADMIN_EMAIL y ADMIN_PASSWORD.');

            return;
        }

        $email = mb_strtolower(trim($email));
        if (Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email:rfc'],
            'password' => ['required', Password::min(10)->letters()->mixedCase()->numbers()],
        ])->fails()) {
            throw new RuntimeException('ADMIN_EMAIL debe ser válido y ADMIN_PASSWORD debe tener al menos 10 caracteres, mayúsculas, minúsculas y números.');
        }

        $rol = Role::where('nombre', 'Administrador')->firstOrFail();
        $existente = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existente) {
            User::updateOrCreate(['id' => $existente->id], ['role_id' => $rol->id, 'status' => true]);

            return;
        }

        User::updateOrCreate(['email' => $email], [
            'first_name' => 'Administrador', 'last_name' => 'Sistema',
            'password' => $password, 'debe_cambiar_password' => true,
            'role_id' => $rol->id, 'status' => true,
        ]);
    }
}
