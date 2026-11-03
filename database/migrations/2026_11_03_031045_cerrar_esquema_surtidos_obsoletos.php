<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Cierre ordinario: únicamente estructuras vacías; el historial se conserva. */
return new class extends Migration
{
    private const TABLAS = ['pedido_despacho_componentes', 'paquete_surtido_componentes', 'paquete_surtido_composiciones', 'pedido_paquetes_surtidos', 'formato_surtido_skus', 'formatos_surtidos'];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Motor no verificado. No se ha iniciado DDL.');
        }
        $mysql = DB::getDriverName() === 'mysql';
        if ($mysql && (int) DB::selectOne("SELECT GET_LOCK('dhacer_cierre_surtidos', 0) adquirido")->adquirido !== 1) {
            throw new RuntimeException('Otro retiro está en curso. No se ha iniciado DDL.');
        }
        try {
            $this->retirar();
        } finally {
            if ($mysql) {
                DB::selectOne("SELECT RELEASE_LOCK('dhacer_cierre_surtidos') liberado");
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
        $this->verificarDependenciasFisicas();
        if ($presentes === [] && ! Schema::hasColumn('pedido_detalles', 'formato_surtido_id')) {
            $this->verificarEsquemaVigente();
            $this->inspeccionarSql();
            $this->registrarDecision('ya_saneado');

            return;
        }
        if (count($presentes) !== count(self::TABLAS) || ! Schema::hasColumn('pedido_detalles', 'formato_surtido_id')) {
            throw new RuntimeException('Retiro parcial detectado: revisar sin reparar ni reintentar. No se ha iniciado DDL.');
        }
        $this->verificarEsquemaVigente();
        $this->verificarEsquemaHistorico();
        if (DB::table('pedido_detalles')->whereNotNull('tipo_linea')->whereNotIn('tipo_linea', ['normal', 'surtido'])->exists()) {
            throw new RuntimeException('Tipo de línea histórico desconocido. No se ha iniciado DDL.');
        }
        $formato = array_filter(Schema::getForeignKeys('pedido_detalles'), fn (array $fk): bool => $fk['columns'] === ['formato_surtido_id'] && $fk['foreign_table'] === 'formatos_surtidos' && $fk['foreign_columns'] === ['id']);
        if (count($formato) !== 1 || DB::table('pedido_detalles as d')->leftJoin('formatos_surtidos as f', 'f.id', '=', 'd.formato_surtido_id')->whereNotNull('d.formato_surtido_id')->whereNull('f.id')->exists()) {
            throw new RuntimeException('Referencia comercial histórica incompleta. No se ha iniciado DDL.');
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
        $this->inspeccionarSql();
        $historico = DB::table('pedido_detalles')->whereNotNull('formato_surtido_id')->exists()
            || DB::table('pedido_detalles')->whereNotNull('tipo_linea')->where('tipo_linea', '<>', 'normal')->exists();
        foreach (self::TABLAS as $tabla) {
            $historico = DB::table($tabla)->exists() || $historico;
        }
        if ($historico) {
            $this->registrarDecision('historial_conservado');

            return;
        }
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
        $this->registrarDecision('estructuras_vacias_retiradas');
    }

    private function verificarDependenciasFisicas(): void
    {
        foreach (Schema::getTableListing(Schema::getCurrentSchemaListing(), false) as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $clave) {
                if (in_array($clave['foreign_table'], self::TABLAS, true) && ! in_array($tabla, self::TABLAS, true)
                    && ! ($tabla === 'pedido_detalles' && Schema::hasTable('formatos_surtidos') && $clave['foreign_table'] === 'formatos_surtidos' && $clave['columns'] === ['formato_surtido_id'])) {
                    throw new RuntimeException('Dependencia externa desde '.$tabla.'. No se ha iniciado DDL.');
                }
            }
        }
    }

    private function registrarDecision(string $estado): void
    {
        $contexto = ['migracion' => pathinfo(__FILE__, PATHINFO_FILENAME), 'estado_esquema' => $estado,
            'tablas_obsoletas_presentes' => $estado === 'historial_conservado' ? 6 : 0];
        if ($estado === 'historial_conservado') {
            Log::warning('Cierre de surtidos: historial conservado; migración registrada sin sanear el esquema. No requiere repetir el retiro para futuras actualizaciones.', $contexto);
        } else {
            Log::info('Cierre de surtidos verificado.', $contexto);
        }
    }

    private function verificarEsquemaVigente(): void
    {
        foreach (['pedido_detalles' => ['tipo_linea', 'retirado_en'], 'pedido_referencias_fisicas' => ['id', 'pedido_detalle_id', 'presentacion_producto_id', 'cantidad', 'importe_centimos', 'sku', 'descripcion', 'numero_paquete', 'created_at'],
            'pedido_versiones' => ['id'], 'pedido_agrupaciones' => ['id'], 'pedido_agrupacion_detalles' => ['id'], 'pedido_preparaciones' => ['id'],
            'pedido_despachos' => ['id'], 'pedido_despacho_lineas' => ['id'], 'pedido_despacho_agrupaciones' => ['id']] as $tabla => $columnas) {
            if (! Schema::hasColumns($tabla, $columnas)) {
                throw new RuntimeException('Esquema vigente incompleto. No se ha iniciado DDL.');
            }
        }
        foreach (['entrega_pedido_detalles' => 'entrega_id', 'retorno_pedido_detalles' => 'retorno_id'] as $tabla => $operacion) {
            $correctas = array_filter(Schema::getForeignKeys($tabla), fn (array $fk): bool => $fk['columns'] === ['despacho_componente_id', 'pedido_detalle_id']
                && $fk['foreign_table'] === 'pedido_referencias_fisicas' && $fk['foreign_columns'] === ['id', 'pedido_detalle_id']);
            $unicas = array_filter(Schema::getIndexes($tabla), fn (array $indice): bool => $indice['unique'] && $indice['columns'] === [$operacion, 'pedido_detalle_id', 'referencia_fisica']);
            $generadas = array_filter(Schema::getColumns($tabla), function (array $columna): bool {
                $expresion = $columna['generation']['expression'] ?? '';

                return $columna['name'] === 'referencia_fisica'
                    && strtolower(preg_replace('/[\\s`"()]/', '', $expresion)) === 'coalescedespacho_componente_id,0';
            });
            if ($correctas === [] || $unicas === [] || $generadas === []) {
                throw new RuntimeException('Referencias físicas vigentes incompletas. No se ha iniciado DDL.');
            }
        }
    }

    private function verificarEsquemaHistorico(): void
    {
        $this->verificarTriggers();
        $unicas = [
            'formatos_surtidos' => [['id']],
            'formato_surtido_skus' => [['id'], ['formato_surtido_id', 'presentacion_producto_id']],
            'pedido_paquetes_surtidos' => [['id'], ['pedido_detalle_id', 'numero']],
            'paquete_surtido_composiciones' => [['id'], ['paquete_surtido_id', 'version']],
            'paquete_surtido_componentes' => [['id'], ['composicion_id', 'presentacion_producto_id']],
            'pedido_despacho_componentes' => [['id'], ['componente_id'], ['id', 'pedido_detalle_id'], ['paquete_surtido_id', 'presentacion_producto_id']],
        ];
        foreach ($unicas as $tabla => $columnas) {
            $reales = array_values(array_map(fn (array $indice): array => $indice['columns'], array_filter(Schema::getIndexes($tabla), fn (array $indice): bool => $indice['unique'])));
            sort($columnas);
            sort($reales);
            if ($columnas !== $reales) {
                throw new RuntimeException('Unicidad histórica desconocida o incompleta. No se ha iniciado DDL.');
            }
        }
        $esperadas = [
            'formatos_surtidos' => ['id', 'producto_id', 'nombre', 'tipo_envase', 'contenido', 'unidad_medida', 'unidades_por_empaque', 'precio_empaque', 'estado', 'version', 'created_at', 'updated_at'],
            'formato_surtido_skus' => ['id', 'formato_surtido_id', 'presentacion_producto_id'],
            'pedido_paquetes_surtidos' => ['id', 'pedido_detalle_id', 'numero', 'version', 'activo'],
            'paquete_surtido_composiciones' => ['id', 'paquete_surtido_id', 'version', 'estado', 'usuario_id', 'motivo', 'created_at'],
            'paquete_surtido_componentes' => ['id', 'composicion_id', 'presentacion_producto_id', 'cantidad', 'sku', 'descripcion'],
            'pedido_despacho_componentes' => ['id', 'pedido_detalle_id', 'paquete_surtido_id', 'componente_id', 'presentacion_producto_id', 'cantidad', 'importe_centimos', 'sku', 'descripcion', 'numero_paquete', 'created_at'],
        ];
        $destinos = ['producto_id' => 'productos', 'formato_surtido_id' => 'formatos_surtidos', 'presentacion_producto_id' => 'presentaciones_producto',
            'pedido_detalle_id' => 'pedido_detalles', 'paquete_surtido_id' => 'pedido_paquetes_surtidos', 'usuario_id' => 'users',
            'composicion_id' => 'paquete_surtido_composiciones', 'componente_id' => 'paquete_surtido_componentes'];
        foreach ($esperadas as $tabla => $columnas) {
            $reales = Schema::getColumnListing($tabla);
            sort($columnas);
            sort($reales);
            if ($reales !== $columnas) {
                throw new RuntimeException('Estructura histórica desconocida. No se ha iniciado DDL.');
            }
            $fkEsperadas = array_intersect_key($destinos, array_flip($columnas));
            foreach (Schema::getIndexes($tabla) as $indice) {
                if (! $indice['unique'] && (count($indice['columns']) !== 1 || ! array_key_exists($indice['columns'][0], $fkEsperadas))) {
                    throw new RuntimeException('Índice histórico desconocido. No se ha iniciado DDL.');
                }
            }
            $fkReales = Schema::getForeignKeys($tabla);
            if (count($fkReales) !== count($fkEsperadas)) {
                throw new RuntimeException('Claves históricas incompletas. No se ha iniciado DDL.');
            }
            foreach ($fkReales as $fk) {
                if (count($fk['columns']) !== 1 || ($fkEsperadas[$fk['columns'][0]] ?? null) !== $fk['foreign_table'] || $fk['foreign_columns'] !== ['id']) {
                    throw new RuntimeException('Clave histórica desconocida. No se ha iniciado DDL.');
                }
            }
        }
        foreach (self::TABLAS as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $fk) {
                $huerfanos = DB::table($tabla.' as h')->leftJoin($fk['foreign_table'].' as p', function ($join) use ($fk): void {
                    foreach ($fk['columns'] as $i => $columna) {
                        $join->on('h.'.$columna, '=', 'p.'.$fk['foreign_columns'][$i]);
                    }
                })->whereNull('p.'.$fk['foreign_columns'][0]);
                foreach ($fk['columns'] as $columna) {
                    $huerfanos->whereNotNull('h.'.$columna);
                }
                if ($huerfanos->exists()) {
                    throw new RuntimeException('Referencias históricas huérfanas. No se ha iniciado DDL.');
                }
            }
        }
    }

    private function verificarTriggers(): void
    {
        $esperados = ['despacho_surtido_propietario'];
        foreach (['paquete_surtido_composiciones', 'paquete_surtido_componentes', 'pedido_despacho_componentes'] as $tabla) {
            foreach (['update', 'delete'] as $evento) {
                $esperados[] = $tabla.'_inmutable_'.$evento;
            }
        }
        if (DB::getDriverName() === 'sqlite') {
            foreach (['formatos_surtidos', 'pedido_paquetes_surtidos', 'paquete_surtido_composiciones', 'paquete_surtido_componentes', 'pedido_despacho_componentes'] as $tabla) {
                foreach (['insert', 'update'] as $evento) {
                    $esperados[] = $tabla.'_valido_'.$evento;
                }
            }
            $reales = DB::table('sqlite_master')->where('type', 'trigger')->whereIn('tbl_name', self::TABLAS)->pluck('name')->all();
        } else {
            $reales = DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->whereIn('EVENT_OBJECT_TABLE', self::TABLAS)->pluck('TRIGGER_NAME')->all();
        }
        sort($esperados);
        sort($reales);
        if ($esperados !== $reales) {
            throw new RuntimeException('Triggers históricos desconocidos o incompletos. No se ha iniciado DDL.');
        }
    }

    private function inspeccionarSql(): void
    {
        $base = DB::getDatabaseName();
        $patron = '/\b('.implode('|', [...self::TABLAS, 'formato_surtido_id']).')\b/i';
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::table('sqlite_master')->whereIn('type', ['trigger', 'view', 'table'])->get(['tbl_name as tabla', 'sql', 'type']) as $objeto) {
                if (in_array($objeto->tabla, self::TABLAS, true)) {
                    continue;
                }
                if ($objeto->tabla === 'pedido_detalles' && $objeto->type === 'table' && Schema::hasColumn('pedido_detalles', 'formato_surtido_id')) {
                    if (! is_string($objeto->sql) || preg_match_all('/\bformato_surtido_id\b/i', $objeto->sql) !== 2
                        || preg_match_all('/\bformatos_surtidos\b/i', $objeto->sql) !== 1
                        || preg_match('/\b('.implode('|', array_diff(self::TABLAS, ['formatos_surtidos'])).')\b/i', $objeto->sql)) {
                        throw new RuntimeException('Definición comercial SQLite desconocida. No se ha iniciado DDL.');
                    }

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
                    if (! is_string($fila->sql)) {
                        throw new RuntimeException('Trigger histórico ilegible. No se ha iniciado DDL.');
                    }

                    continue;
                }
                $cualificado = '/\b'.preg_quote($base, '/').'\s*\.\s*('.implode('|', self::TABLAS).')\b/i';
                $columnaCualificada = '/\b'.preg_quote($base, '/').'\s*\.\s*pedido_detalles\b/i';
                if (! is_string($fila->sql) || preg_match($fila->esquema === $base ? $patron : $cualificado, str_replace('`', '', $fila->sql))
                    || (preg_match($columnaCualificada, str_replace('`', '', $fila->sql)) && preg_match('/\bformato_surtido_id\b/i', $fila->sql))) {
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
