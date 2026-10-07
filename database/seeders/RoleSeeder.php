<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate([
            'nombre' => 'Administrador',
        ]);

        Role::updateOrCreate([
            'nombre' => 'Operaciones Comerciales',
        ]);

        Role::updateOrCreate([
            'nombre' => 'Producción/Reparto',
        ]);
    }
}