<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Retiro manual autorizado; fuera del recorrido de migraciones ordinarias. */
return new class extends Migration
{
    private const TABLAS = ['pedido_despacho_componentes', 'paquete_surtido_componentes', 'paquete_surtido_composiciones', 'pedido_paquetes_surtidos', 'formato_surtido_skus', 'formatos_surtidos'];

    public function up(): void
    {
        $base = DB::getDatabaseName();
        $mysql = DB::getDriverName() === 'mysql';
        $pruebas = app()->environment('testing') && ((! $mysql && DB::getDriverName() === 'sqlite' && $base === ':memory:')
            || ($mysql && getenv('MYSQL_TEST_ENABLE') === '1' && preg_match('/\Adhacer_eco_limp_test_[a-z0-9_]+\z/', $base)));
        if (getenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO') !== 'RETIRAR_ESTRUCTURAS_SURTIDOS_OBSOLETAS' || (! $pruebas && (! $mysql || $base !== 'software_gestion_web_microempresa'))) {
            throw new RuntimeException('Retiro bloqueado: autorización o base incorrectas. No se ha iniciado DDL.');
        }
        if (! $pruebas) {
            $i = DB::selectOne('SELECT @@server_uuid uuid, @@port puerto, @@datadir directorio, VERSION() version');
            $normalizar = fn (string $ruta): string => strtolower(rtrim(str_replace('\\', '/', $ruta), '/'));
            $config = DB::connection()->getConfig();
            if (! getenv('DHACER_RETIRO_MYSQL_UUID') || getenv('DHACER_RETIRO_MYSQL_UUID') !== $i->uuid
                || ! getenv('DHACER_RETIRO_MYSQL_DATADIR') || $normalizar(getenv('DHACER_RETIRO_MYSQL_DATADIR')) !== $normalizar($i->directorio)
                || $config['host'] !== '127.0.0.1' || (int) $config['port'] !== 3306 || (int) $i->puerto !== 3306 || $i->version !== '8.4.3') {
                throw new RuntimeException('Identidad MySQL distinta de la autorizada. No se ha iniciado DDL.');
            }
        }
        if ($mysql && (int) DB::selectOne("SELECT GET_LOCK('dhacer_retiro_surtidos', 0) adquirido")->adquirido !== 1) {
            throw new RuntimeException('Otro retiro está en curso. No se ha iniciado DDL.');
        }
        try {
            $this->retirar();
        } finally {
            if ($mysql) {
                DB::selectOne("SELECT RELEASE_LOCK('dhacer_retiro_surtidos') liberado");
            }
        }
    }

    private function retirar(): void
    {
        foreach (['pedido_referencias_fisicas', 'pedido_agrupaciones', 'pedido_despacho_agrupaciones', 'pedido_despacho_lineas'] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                throw new RuntimeException('Falta el esquema vigente de unidades. No se ha iniciado DDL.');
            }
        }
        $presentes = array_filter(self::TABLAS, fn (string $tabla): bool => Schema::hasTable($tabla));
        if ($presentes === [] && ! Schema::hasColumn('pedido_detalles', 'formato_surtido_id')) {
            return;
        }
        if (count($presentes) !== count(self::TABLAS) || ! Schema::hasColumn('pedido_detalles', 'formato_surtido_id')) {
            throw new RuntimeException('Retiro parcial detectado: revisar sin reparar ni reintentar. No se ha iniciado DDL.');
        }
        if (DB::table('pedido_detalles')->whereNotNull('formato_surtido_id')->exists()
            || DB::table('pedido_detalles')->whereNotNull('tipo_linea')->where('tipo_linea', '<>', 'normal')->exists()) {
            throw new RuntimeException('Hay líneas comerciales históricas vinculadas al modelo retirado. No se ha iniciado DDL.');
        }
        $columnas = ['id', 'pedido_detalle_id', 'presentacion_producto_id', 'cantidad', 'importe_centimos', 'sku', 'descripcion', 'numero_paquete', 'created_at'];
        foreach (DB::table('pedido_despacho_componentes')->orderBy('id')->cursor() as $anterior) {
            $referencia = DB::table('pedido_referencias_fisicas')->where('id', $anterior->id)->first();
            foreach ($columnas as $columna) {
                if (! $referencia || (string) $referencia->$columna !== (string) $anterior->$columna) {
                    throw new RuntimeException('Salida histórica sin fotografía física equivalente. No se ha iniciado DDL.');
                }
            }
        }
        foreach (Schema::getIndexes('pedido_detalles') as $indice) {
            if (in_array('formato_surtido_id', $indice['columns'], true) && ($indice['unique'] || $indice['columns'] !== ['formato_surtido_id'])) {
                throw new RuntimeException('Índice externo inesperado sobre formato_surtido_id. No se ha iniciado DDL.');
            }
        }
        foreach (Schema::getTableListing(null, false) as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $clave) {
                if (in_array($clave['foreign_table'], self::TABLAS, true) && ! in_array($tabla, self::TABLAS, true)
                    && ! ($tabla === 'pedido_detalles' && $clave['foreign_table'] === 'formatos_surtidos' && $clave['columns'] === ['formato_surtido_id'])) {
                    throw new RuntimeException('Dependencia externa desde '.$tabla.'. No se ha iniciado DDL.');
                }
            }
        }
        $this->inspeccionarSql();
        $triggers = DB::getDriverName() === 'sqlite'
            ? DB::table('sqlite_master')->where('type', 'trigger')->whereRaw("instr(lower(sql), 'pedido_detalles') > 0")->get(['name', 'sql']) : collect();
        foreach ($triggers as $trigger) {
            if (! is_string($trigger->sql) || ! str_starts_with($trigger->sql, 'CREATE TRIGGER') || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $trigger->name)) {
                throw new RuntimeException('Trigger SQLite no verificable. No se ha iniciado DDL.');
            }
        }
        foreach ($triggers as $trigger) {
            DB::unprepared('DROP TRIGGER '.$trigger->name);
        }
        foreach (Schema::getForeignKeys('pedido_detalles') as $clave) {
            if ($clave['foreign_table'] === 'formatos_surtidos') {
                Schema::table('pedido_detalles', fn (Blueprint $tabla) => $tabla->dropForeign($clave['name'] ?? $clave['columns']));
            }
        }
        Schema::table('pedido_detalles', fn (Blueprint $tabla) => $tabla->dropColumn('formato_surtido_id'));
        foreach ($triggers as $trigger) {
            if (DB::connection()->getPdo()->exec($trigger->sql) === false) {
                throw new RuntimeException('No se restableció un trigger vigente: detener sin reparar automáticamente.');
            }
        }
        foreach (self::TABLAS as $tabla) {
            Schema::drop($tabla);
        }
    }

    private function inspeccionarSql(): void
    {
        $base = DB::getDatabaseName();
        $patron = '/\b('.implode('|', [...self::TABLAS, 'formato_surtido_id']).')\b/i';
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::table('sqlite_master')->whereIn('type', ['trigger', 'view', 'table'])->get(['tbl_name as tabla', 'sql', 'type']) as $objeto) {
                if (in_array($objeto->tabla, self::TABLAS, true) || ($objeto->tabla === 'pedido_detalles' && $objeto->type === 'table')) {
                    continue;
                }
                if (! is_string($objeto->sql) || preg_match($patron, $objeto->sql)) {
                    throw new RuntimeException('Objeto SQL externo dependiente o ilegible. No se ha iniciado DDL.');
                }
            }

            return;
        }
        if (DB::table('information_schema.KEY_COLUMN_USAGE')->where('REFERENCED_TABLE_SCHEMA', $base)->whereIn('REFERENCED_TABLE_NAME', self::TABLAS)->where('TABLE_SCHEMA', '<>', $base)->exists()) {
            throw new RuntimeException('Otra base referencia las tablas retiradas. No se ha iniciado DDL.');
        }
        foreach ([['TRIGGERS', 'TRIGGER_SCHEMA', 'ACTION_STATEMENT', 'EVENT_OBJECT_TABLE'], ['VIEWS', 'TABLE_SCHEMA', 'VIEW_DEFINITION', 'TABLE_NAME'], ['ROUTINES', 'ROUTINE_SCHEMA', 'ROUTINE_DEFINITION', 'ROUTINE_NAME'], ['EVENTS', 'EVENT_SCHEMA', 'EVENT_DEFINITION', 'EVENT_NAME']] as [$catalogo, $esquema, $definicion, $objeto]) {
            foreach (DB::table('information_schema.'.$catalogo)->whereNotIn($esquema, ['mysql', 'sys', 'information_schema', 'performance_schema'])->get([$esquema.' as esquema', $definicion.' as sql', $objeto.' as objeto']) as $fila) {
                if ($catalogo === 'TRIGGERS' && $fila->esquema === $base && in_array($fila->objeto, self::TABLAS, true)) {
                    continue;
                }
                $cualificado = '/\b'.preg_quote($base, '/').'\s*\.\s*('.implode('|', self::TABLAS).')\b/i';
                if (! is_string($fila->sql) || preg_match($fila->esquema === $base ? $patron : $cualificado, str_replace('`', '', $fila->sql))) {
                    throw new RuntimeException('Trigger, vista, rutina o evento dependiente o ilegible. No se ha iniciado DDL.');
                }
            }
        }
        foreach (DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', $base)->whereNotIn('TABLE_NAME', self::TABLAS)->where('GENERATION_EXPRESSION', '<>', '')->pluck('GENERATION_EXPRESSION') as $expresion) {
            if (preg_match($patron, $expresion)) {
                throw new RuntimeException('Columna generada dependiente. No se ha iniciado DDL.');
            }
        }
        foreach (DB::table('information_schema.CHECK_CONSTRAINTS as c')->join('information_schema.TABLE_CONSTRAINTS as t', fn ($join) => $join->on('c.CONSTRAINT_SCHEMA', '=', 't.CONSTRAINT_SCHEMA')->on('c.CONSTRAINT_NAME', '=', 't.CONSTRAINT_NAME'))->where('t.TABLE_SCHEMA', $base)->whereNotIn('t.TABLE_NAME', self::TABLAS)->pluck('c.CHECK_CLAUSE') as $expresion) {
            if (preg_match($patron, $expresion)) {
                throw new RuntimeException('Restricción CHECK dependiente. No se ha iniciado DDL.');
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Retiro irreversible: no se recrean datos obsoletos mediante rollback.');
    }
};
