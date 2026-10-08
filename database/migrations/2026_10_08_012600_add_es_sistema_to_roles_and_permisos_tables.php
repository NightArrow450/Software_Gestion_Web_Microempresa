<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('es_sistema')
                ->default(false)
                ->after('nombre');
        });

        Schema::table('permisos', function (Blueprint $table) {
            $table->boolean('es_sistema')
                ->default(false)
                ->after('descripcion');
        });

        // Los 3 roles actuales son roles base del sistema.
        DB::table('roles')
            ->whereIn('nombre', [
                'Administrador',
                'Operaciones Comerciales',
                'Producción/Reparto',
            ])
            ->update([
                'es_sistema' => true,
            ]);

        // Todos los permisos existentes hasta este momento
        // corresponden a los permisos base del sistema.
        DB::table('permisos')
            ->update([
                'es_sistema' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('es_sistema');
        });

        Schema::table('permisos', function (Blueprint $table) {
            $table->dropColumn('es_sistema');
        });
    }
};