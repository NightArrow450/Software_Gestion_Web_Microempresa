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
                    && ! in_array($tabla, [...self::TABLAS, 'pedido_detalles', 'entrega_pedido_detalles', 'retorno_pedido_detalles'], true)) {
                    throw new RuntimeException('Dependencia no prevista desde '.$tabla.'. No se ha iniciado el retiro.');
                }
            }
        }
        foreach (['entrega_pedido_detalles' => 'entrega_id', 'retorno_pedido_detalles' => 'retorno_id'] as $tabla => $operacion) {
            foreach (['insert', 'update'] as $evento) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$operacion.'_componente_'.$evento);
            }
            foreach (Schema::getForeignKeys($tabla) as $foreign) {
                if ($foreign['foreign_table'] === 'pedido_despacho_componentes') {
                    Schema::table($tabla, fn (Blueprint $t) => $t->dropForeign($foreign['name'] ?? $foreign['columns']));
                }
            }
            Schema::table($tabla, fn (Blueprint $t) => $t->foreign(['despacho_componente_id', 'pedido_detalle_id'], $operacion.'_referencia_fk')
                ->references(['id', 'pedido_detalle_id'])->on('pedido_referencias_fisicas')->restrictOnDelete());
            $valida = 'EXISTS (SELECT 1 FROM pedido_detalles d WHERE d.id = NEW.pedido_detalle_id AND ((NEW.despacho_componente_id IS NULL AND d.presentacion_producto_id IS NOT NULL) OR EXISTS (SELECT 1 FROM pedido_referencias_fisicas r WHERE r.id = NEW.despacho_componente_id AND r.pedido_detalle_id = d.id)))';
            foreach (['INSERT', 'UPDATE'] as $evento) {
                $nombre = $operacion.'_referencia_'.strtolower($evento);
                DB::unprepared(DB::getDriverName() === 'mysql'
                    ? "CREATE TRIGGER $nombre BEFORE $evento ON $tabla FOR EACH ROW BEGIN IF NOT ($valida) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Referencia fisica ajena o ausente'; END IF; END"
                    : "CREATE TRIGGER $nombre BEFORE $evento ON $tabla WHEN NOT ($valida) BEGIN SELECT RAISE(ABORT, 'Referencia física ajena o ausente'); END");
            }
        }
        $externos = DB::getDriverName() === 'sqlite'
            ? DB::table('sqlite_master')->where('type', 'trigger')->where('tbl_name', '<>', 'pedido_detalles')->whereRaw("instr(lower(sql), 'pedido_detalles') > 0")->get(['name', 'sql'])
            : collect();
        foreach ($externos as $trigger) {
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
