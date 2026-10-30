<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Compatibilidad de despliegues anteriores: no crea ni modifica autorizaciones retiradas. */
class SurtidosPermisoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('Las ventas surtidas fueron retiradas; no se modificaron usuarios ni permisos existentes.');
    }
}
