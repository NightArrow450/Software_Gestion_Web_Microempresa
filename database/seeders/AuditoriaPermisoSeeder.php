<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AuditoriaPermisoSeeder extends Seeder
{
    public function run(): void
    {
        $permiso = Permiso::updateOrCreate(['nombre' => 'auditoria.ver'], ['descripcion' => 'Permite consultar la auditoría de acciones sensibles.']);
        Role::where('nombre', 'Administrador')->firstOrFail()->permisos()->syncWithoutDetaching([$permiso->id]);
    }
}
