<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

class IndicadoresPermisoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permisos = [
            'indicadores.ver' => 'Consultar indicadores comerciales y operativos sin importes.',
            'indicadores.economicos.ver' => 'Consultar importes comerciales y valoraciones informativas.',
            'indicadores.exportar' => 'Exportar los indicadores dentro de los permisos de consulta autorizados.',
        ];
        foreach ($permisos as $nombre => $descripcion) {
            Permiso::updateOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }
        foreach (['Administrador', 'Operaciones Comerciales'] as $nombre) {
            Role::where('nombre', $nombre)->firstOrFail()->permisos()->syncWithoutDetaching(Permiso::whereIn('nombre', array_keys($permisos))->pluck('id'));
        }
    }
}
