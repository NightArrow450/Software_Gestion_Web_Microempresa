<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

class EntregaRetornoPermisoSeeder extends Seeder
{
    public const PERMISOS = [
        'cumplimiento.retornos.registrar' => 'Registrar recepción física y no recuperación de productos rechazados.',
        'cumplimiento.retornos.aprobar' => 'Autorizar reincorporaciones parciales de productos aptos recibidos.',
        'cumplimiento.incidencias.revisar' => 'Revisar incidencias y clasificar productos recibidos.',
        'cumplimiento.incidencias.resolver' => 'Documentar la resolución comercial de incidencias de entrega.',
        'cumplimiento.incidencias.corregir' => 'Rectificar resultados de entrega y reabrir expedientes con auditoría.',
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $nombre => $descripcion) {
            Permiso::updateOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }
        foreach (['Administrador' => array_keys(self::PERMISOS), 'Operaciones Comerciales' => array_diff(array_keys(self::PERMISOS), ['cumplimiento.incidencias.corregir']), 'Producción/Reparto' => ['cumplimiento.retornos.registrar']] as $nombre => $permisos) {
            Role::where('nombre', $nombre)->firstOrFail()->permisos()->syncWithoutDetaching(Permiso::whereIn('nombre', $permisos)->pluck('id'));
        }
    }
}
