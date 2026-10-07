<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $administrador = Role::where(
            'nombre',
            'Administrador'
        )->firstOrFail();

        User::updateOrCreate(
            [
                'email' => 'admin@local.test',
            ],
            [
                'first_name' => 'Administrador',
                'last_name' => 'Sistema',
                'password' => 'Admin12345!',
                'role_id' => $administrador->id,
                'status' => true,
            ]
        );
    }
}