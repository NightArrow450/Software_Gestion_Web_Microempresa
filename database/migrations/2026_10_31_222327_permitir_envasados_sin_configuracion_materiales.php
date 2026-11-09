<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $triggers = DB::getDriverName() === 'sqlite'
            ? DB::table('sqlite_master')->where('type', 'trigger')->where(fn ($q) => $q->where('tbl_name', 'envasados')->orWhereRaw("instr(lower(sql), 'envasados') > 0"))->get(['name', 'sql'])
            : collect();
        foreach ($triggers as $trigger) {
            if (! is_string($trigger->sql) || ! str_starts_with($trigger->sql, 'CREATE TRIGGER') || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $trigger->name)) {
                throw new RuntimeException('No se puede verificar un trigger de envasado. Detener sin modificar datos.');
            }
        }
        foreach ($triggers as $trigger) {
            DB::unprepared('DROP TRIGGER '.$trigger->name);
        }
        Schema::table('envasados', fn (Blueprint $tabla) => $tabla->unsignedBigInteger('configuracion_envasado_id')->nullable()->change());
        foreach ($triggers as $trigger) {
            if (DB::connection()->getPdo()->exec($trigger->sql) === false) {
                throw new RuntimeException('No se restableció un trigger de envasado. Detener sin reparaciones automáticas.');
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('No se exige retroactivamente una configuración a los envasados sin consumos registrados.');
    }
};
