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
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->string('origen_tipo', 40)->nullable();
            $table->string('origen_id', 64)->nullable();
            $table->unique(['origen_tipo', 'origen_id', 'presentacion_producto_id'], 'movimientos_origen_producto_unico');
            $table->unique(['origen_tipo', 'origen_id', 'insumo_id'], 'movimientos_origen_insumo_unico');
        });
        DB::table('movimientos_inventario')->whereNotNull('pedido_id')->update([
            'origen_tipo' => 'despacho', 'origen_id' => DB::raw('CAST(pedido_id AS CHAR)'),
        ]);
        $condicion = "((presentacion_producto_id IS NOT NULL AND insumo_id IS NULL) OR (presentacion_producto_id IS NULL AND insumo_id IS NOT NULL)) AND ((origen_tipo IS NULL AND origen_id IS NULL) OR (origen_tipo IS NOT NULL AND origen_id IS NOT NULL AND origen_tipo <> '' AND origen_id <> '')) AND (pedido_id IS NULL OR (tipo = 'salida' AND presentacion_producto_id IS NOT NULL AND origen_tipo IS NOT NULL AND origen_tipo = 'despacho' AND origen_id = CAST(pedido_id AS CHAR))) AND (origen_tipo IS NULL OR origen_tipo <> 'despacho' OR pedido_id IS NOT NULL)";
        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $evento) {
                $columnas = ['presentacion_producto_id', 'insumo_id', 'origen_tipo', 'origen_id', 'pedido_id', 'tipo'];
                $expresion = preg_replace('/\\b('.implode('|', $columnas).')\\b/', 'NEW.$1', $condicion);
                DB::unprepared('CREATE TRIGGER movimientos_origen_'.strtolower($evento)." BEFORE $evento ON movimientos_inventario WHEN NOT ($expresion) BEGIN SELECT RAISE(ABORT, 'Origen o destino de movimiento inválido'); END");
            }
        } else {
            DB::statement("ALTER TABLE movimientos_inventario ADD CONSTRAINT movimientos_origen_valido CHECK ($condicion)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS movimientos_origen_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS movimientos_origen_update');
        } else {
            DB::statement('ALTER TABLE movimientos_inventario DROP CHECK movimientos_origen_valido');
        }
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropUnique('movimientos_origen_producto_unico');
            $table->dropUnique('movimientos_origen_insumo_unico');
            $table->dropColumn(['origen_tipo', 'origen_id']);
        });
    }
};
