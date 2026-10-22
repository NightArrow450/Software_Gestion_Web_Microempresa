<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('movimientos_inventario')->whereRaw('(presentacion_producto_id IS NULL AND insumo_id IS NULL) OR (presentacion_producto_id IS NOT NULL AND insumo_id IS NOT NULL)')->exists()
            || DB::table('movimientos_inventario')->whereNotNull('pedido_id')->where(function ($query) {
                $query->where('tipo', '<>', 'salida')->orWhereNull('presentacion_producto_id');
            })->exists()) {
            throw new RuntimeException('Hay movimientos con destino u origen de despacho inconsistente. Revísalos antes de migrar; no se modificó ningún dato.');
        }
        foreach (['origen_tipo' => 40, 'origen_id' => 64] as $columna => $longitud) {
            if (! Schema::hasColumn('movimientos_inventario', $columna)) {
                Schema::table('movimientos_inventario', fn (Blueprint $table) => $table->string($columna, $longitud)->nullable());
            }
        }
        $indices = array_column(Schema::getIndexes('movimientos_inventario'), 'name');
        foreach (['producto' => 'presentacion_producto_id', 'insumo' => 'insumo_id'] as $destino => $columna) {
            $nombre = 'movimientos_origen_'.$destino.'_unico';
            if (! in_array($nombre, $indices, true)) {
                Schema::table('movimientos_inventario', fn (Blueprint $table) => $table->unique(['origen_tipo', 'origen_id', $columna], $nombre));
            }
        }
        DB::table('movimientos_inventario')->whereNotNull('pedido_id')->whereNull('origen_tipo')->whereNull('origen_id')->update([
            'origen_tipo' => 'despacho', 'origen_id' => DB::raw('CAST(pedido_id AS CHAR)'),
        ]);
        $condicion = "((presentacion_producto_id IS NOT NULL AND insumo_id IS NULL) OR (presentacion_producto_id IS NULL AND insumo_id IS NOT NULL)) AND ((origen_tipo IS NULL AND origen_id IS NULL) OR (origen_tipo IS NOT NULL AND origen_id IS NOT NULL AND origen_tipo <> '' AND origen_id <> '')) AND (pedido_id IS NULL OR (tipo = 'salida' AND presentacion_producto_id IS NOT NULL AND origen_tipo IS NOT NULL AND origen_tipo = 'despacho' AND origen_id = CAST(pedido_id AS CHAR))) AND (origen_tipo IS NULL OR origen_tipo <> 'despacho' OR pedido_id IS NOT NULL)";
        if (DB::getDriverName() === 'mysql') {
            $condicion = str_replace('origen_id = CAST(pedido_id AS CHAR)', 'CAST(origen_id AS BINARY) = CAST(pedido_id AS BINARY)', $condicion);
        }
        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $evento) {
                $columnas = ['presentacion_producto_id', 'insumo_id', 'origen_tipo', 'origen_id', 'pedido_id', 'tipo'];
                $expresion = preg_replace('/\\b('.implode('|', $columnas).')\\b/', 'NEW.$1', $condicion);
                if ($evento === 'INSERT') {
                    $expresion = 'NEW.origen_tipo IS NOT NULL AND NEW.origen_id IS NOT NULL AND ('.$expresion.')';
                }
                DB::unprepared('CREATE TRIGGER IF NOT EXISTS movimientos_origen_'.strtolower($evento)." BEFORE $evento ON movimientos_inventario WHEN NOT ($expresion) BEGIN SELECT RAISE(ABORT, 'Origen o destino de movimiento inválido'); END");
            }
        } else {
            if (! DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())->where('CONSTRAINT_NAME', 'movimientos_origen_valido')->exists()) {
                DB::statement("ALTER TABLE movimientos_inventario ADD CONSTRAINT movimientos_origen_valido CHECK ($condicion)");
            }
            if (! DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())->where('TRIGGER_NAME', 'movimientos_exigir_origen')->exists()) {
                DB::unprepared("CREATE TRIGGER movimientos_exigir_origen BEFORE INSERT ON movimientos_inventario FOR EACH ROW BEGIN IF NEW.origen_tipo IS NULL OR NEW.origen_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos nuevos requieren una operacion de origen'; END IF; END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS movimientos_origen_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS movimientos_origen_update');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS movimientos_exigir_origen');
            DB::statement('ALTER TABLE movimientos_inventario DROP CHECK movimientos_origen_valido');
        }
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropUnique('movimientos_origen_producto_unico');
            $table->dropUnique('movimientos_origen_insumo_unico');
            $table->dropColumn(['origen_tipo', 'origen_id']);
        });
    }
};
