<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rolId = DB::table('roles')
            ->where('nombre', 'Operaciones Comerciales')
            ->value('id');

        $permisoId = DB::table('permisos')
            ->where('nombre', 'pedidos.cancelar')
            ->value('id');

        /*
        |--------------------------------------------------------------------------
        | Asignar permiso a Operaciones Comerciales
        |--------------------------------------------------------------------------
        |
        | insertOrIgnore evita duplicar la relación
        | cuando el permiso ya estaba asignado.
        |
        */

        if ($rolId && $permisoId) {
            DB::table('rol_permiso')->insertOrIgnore([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No se eliminan asignaciones anteriores.
        // El rollback conserva los permisos existentes.
    }
};
