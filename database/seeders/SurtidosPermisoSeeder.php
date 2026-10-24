<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SurtidosPermisoSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = ['surtidos.configurar' => 'Habilitar modalidades surtidas, precios y SKU compatibles.',
            'pedidos.surtidos.componer' => 'Definir composiciones individuales antes del cierre de preparación.'];
        foreach ($permisos as $nombre => $descripcion) {
            Permiso::updateOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }
        foreach (['Administrador', 'Operaciones Comerciales'] as $nombre) {
            Role::where('nombre', $nombre)->firstOrFail()->permisos()->syncWithoutDetaching(Permiso::whereIn('nombre', array_keys($permisos))->pluck('id'));
        }
    }
}
