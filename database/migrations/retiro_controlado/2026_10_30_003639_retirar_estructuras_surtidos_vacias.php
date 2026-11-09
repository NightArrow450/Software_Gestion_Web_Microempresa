<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fuera del recorrido ordinario: requiere autorización explícita y tablas obsoletas vacías. */
return new class extends Migration
{
    private const TABLAS = ['pedido_despacho_componentes', 'paquete_surtido_componentes', 'paquete_surtido_composiciones', 'pedido_paquetes_surtidos', 'formato_surtido_skus', 'formatos_surtidos'];

    public function up(): void
    {
        $base = DB::getDatabaseName();
        $pruebas = app()->environment('testing') && (($base === ':memory:' && DB::getDriverName() === 'sqlite')
            || (getenv('MYSQL_TEST_ENABLE') === '1' && preg_match('/\Adhacer_eco_limp_test_[a-z0-9_]+\z/', $base)));
        if (getenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO') !== 'RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS'
            || (! $pruebas && $base !== 'software_gestion_web_microempresa')) {
            throw new RuntimeException('Retiro bloqueado: falta autorización específica o la base no está permitida.');
        }
        if (! Schema::hasTable('pedido_referencias_fisicas') || ! Schema::hasTable('pedido_despacho_agrupaciones')) {
            throw new RuntimeException('Falta completar el esquema de unidades y referencias físicas.');
        }
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || DB::table($tabla)->exists()) {
                throw new RuntimeException('No se retira '.$tabla.': debe existir y estar vacía. No se ha eliminado ningún dato.');
            }
        }
        if (DB::table('pedido_detalles')->whereNotNull('formato_surtido_id')->exists()
            || DB::table('pedido_detalles')->where('tipo_linea', '<>', 'normal')->exists()) {
            throw new RuntimeException('Hay líneas anteriores pendientes de saneamiento. No se convierten precios ni cantidades.');
        }
        foreach (Schema::getIndexes('pedido_detalles') as $indice) {
            if (in_array('formato_surtido_id', $indice['columns'], true)
                && ($indice['unique'] || $indice['columns'] !== ['formato_surtido_id'])) {
                throw new RuntimeException('Índice no previsto sobre la referencia antigua. No se ha iniciado el retiro.');
            }
        }
        foreach (Schema::getTableListing(null, false) as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $foreign) {
                if (in_array($foreign['foreign_table'], self::TABLAS, true)
                    && ! in_array($tabla, [...self::TABLAS, 'pedido_detalles'], true)) {
                    throw new RuntimeException('Dependencia no prevista desde '.$tabla.'. No se ha iniciado el retiro.');
                }
            }
        }
        $triggers = DB::getDriverName() === 'sqlite'
            ? DB::table('sqlite_master')->where('type', 'trigger')->get(['tbl_name as tabla', 'sql'])
            : DB::table('information_schema.triggers')->where('TRIGGER_SCHEMA', $base)->get(['EVENT_OBJECT_TABLE as tabla', 'ACTION_STATEMENT as sql']);
        $vistas = DB::getDriverName() === 'sqlite'
            ? DB::table('sqlite_master')->where('type', 'view')->get(['sql'])
            : DB::table('information_schema.views')->where('TABLE_SCHEMA', $base)->get(['VIEW_DEFINITION as sql']);
        $patron = '/\b('.implode('|', [...self::TABLAS, 'formato_surtido_id']).')\b/i';
        foreach ($triggers->reject(fn ($t): bool => in_array($t->tabla, self::TABLAS, true))->concat($vistas) as $objeto) {
            if (! is_string($objeto->sql) || preg_match($patron, $objeto->sql)) {
                throw new RuntimeException('Trigger o vista externa depende de las estructuras retiradas o no puede inspeccionarse. No se ha iniciado DDL.');
            }
        }
        $externos = DB::getDriverName() === 'sqlite'
            ? DB::table('sqlite_master')->where('type', 'trigger')->whereRaw("instr(lower(sql), 'pedido_detalles') > 0")->get(['name', 'sql'])
            : collect();
        foreach ($externos as $trigger) {
            if (str_contains(strtolower($trigger->sql ?? ''), 'formato_surtido_id')) {
                throw new RuntimeException('Un trigger depende de la columna retirada; requiere revisión antes de modificar el esquema.');
            }
            if (! is_string($trigger->sql) || ! str_starts_with($trigger->sql, 'CREATE TRIGGER') || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $trigger->name)) {
                throw new RuntimeException('Nombre de trigger inesperado; detener el retiro.');
            }
        }
        foreach ($externos as $trigger) {
            DB::unprepared('DROP TRIGGER '.$trigger->name);
        }
        foreach (Schema::getForeignKeys('pedido_detalles') as $foreign) {
            if ($foreign['foreign_table'] === 'formatos_surtidos') {
                Schema::table('pedido_detalles', fn (Blueprint $t) => $t->dropForeign($foreign['name'] ?? $foreign['columns']));
            }
        }
        Schema::table('pedido_detalles', fn (Blueprint $t) => $t->dropColumn('formato_surtido_id'));
        foreach ($externos as $trigger) {
            if (DB::connection()->getPdo()->exec($trigger->sql) === false) {
                throw new RuntimeException('No se restableció el trigger de integridad. Detener el procedimiento.');
            }
        }
        foreach (self::TABLAS as $tabla) {
            Schema::drop($tabla);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('No se recrean estructuras retiradas ni se restaura información automáticamente.');
    }
};
