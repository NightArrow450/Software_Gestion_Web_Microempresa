<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fixture explícita: prueba que conservar historia no bloquea una migración posterior. */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('testing') || (DB::getDriverName() === 'mysql' && ! str_starts_with(DB::getDatabaseName(), 'dhacer_eco_limp_test_'))
            || (DB::getDriverName() === 'sqlite' && DB::getDatabaseName() !== ':memory:')) {
            throw new RuntimeException('Fixture disponible únicamente en bases aisladas de pruebas.');
        }
        Schema::create('prueba_actualizacion_futura', fn (Blueprint $tabla) => $tabla->id());
    }

    public function down(): void
    {
        throw new RuntimeException('No ejecutar rollback de esta fixture.');
    }
};
