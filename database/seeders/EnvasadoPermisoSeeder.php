<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

class EnvasadoPermisoSeeder extends Seeder
{
    public function run(): void
    {
        $todos = ['envasado.ver', 'envasado.registrar', 'envasado.reportar', 'envasado.corregir', 'materiales.envasado.configurar'];
        foreach ($todos as $nombre) {
            Permiso::updateOrCreate(['nombre' => $nombre], ['descripcion' => match ($nombre) {
                'envasado.ver' => 'Consultar registros de envasado.', 'envasado.registrar' => 'Registrar producción e inventario de envasado.', 'envasado.reportar' => 'Comunicar errores de envasado sin modificar inventario.', 'envasado.corregir' => 'Compensar o anular envasados con auditoría.', default => 'Configurar materiales físicos por presentación.'
            }]);
        }
        foreach (['Administrador' => $todos, 'Operaciones Comerciales' => ['envasado.ver', 'envasado.reportar', 'materiales.envasado.configurar'], 'Producción/Reparto' => ['envasado.ver', 'envasado.registrar', 'envasado.reportar', 'materiales.envasado.configurar']] as $rol => $nombres) {
            Role::where('nombre', $rol)->firstOrFail()->permisos()->syncWithoutDetaching(Permiso::whereIn('nombre', $nombres)->pluck('id'));
        }
    }
}
