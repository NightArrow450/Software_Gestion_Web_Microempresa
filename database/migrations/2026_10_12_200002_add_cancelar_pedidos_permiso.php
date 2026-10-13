<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['nombre' => 'pedidos.cancelar'],
            ['descripcion' => 'Permite cancelar pedidos con motivo.', 'es_sistema' => false,
             'created_at' => now(), 'updated_at' => now()]
        );
        $rolId = DB::table('roles')->where('nombre', 'Administrador')->value('id');
        $permisoId = DB::table('permisos')->where('nombre', 'pedidos.cancelar')->value('id');
        if ($rolId && $permisoId) {
            DB::table('rol_permiso')->insertOrIgnore([
                'rol_id'=>$rolId, 'permiso_id'=>$permisoId,
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void
    {
        // Reversión conservadora: no se borran permisos ni asignaciones anteriores.
    }
};
