<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REFERENCIAS = [
        'variantes_producto' => ['producto_id' => 'productos'],
        'presentaciones_producto' => ['producto_id' => 'productos', 'variante_producto_id' => 'variantes_producto'],
        'pedido_detalles' => ['pedido_id' => 'pedidos', 'presentacion_producto_id' => 'presentaciones_producto'],
        'pedido_estados_historial' => ['pedido_id' => 'pedidos'],
        'pedidos' => ['procesado_por_id' => 'users', 'preparacion_iniciada_por_id' => 'users', 'preparacion_finalizada_por_id' => 'users', 'despachado_por_id' => 'users', 'entregado_por_id' => 'users'],
    ];

    public function up(): void
    {
        foreach (['inventarios', 'insumos'] as $tabla) {
            if (DB::table($tabla)->where('cantidad_actual', '<', 0)->exists()) {
                throw new RuntimeException("Hay saldos negativos en $tabla. La migración no regulariza ni elimina datos.");
            }
        }
        foreach (self::REFERENCIAS as $tabla => $columnas) {
            foreach ($columnas as $columna => $destino) {
                if (DB::table($tabla.' as origen')->leftJoin($destino.' as destino', 'origen.'.$columna, '=', 'destino.id')->whereNotNull('origen.'.$columna)->whereNull('destino.id')->exists()) {
                    throw new RuntimeException("Hay referencias huérfanas en $tabla.$columna. Revísalas antes de migrar.");
                }
            }
        }
        foreach (self::REFERENCIAS as $tabla => $columnas) {
            foreach ($columnas as $columna => $destino) {
                if (DB::getDriverName() === 'mysql') {
                    $nombre = $tabla.'_'.$columna.'_foreign';
                    DB::statement("ALTER TABLE `$tabla` DROP FOREIGN KEY `$nombre`, ADD CONSTRAINT `$nombre` FOREIGN KEY (`$columna`) REFERENCES `$destino` (`id`) ON DELETE RESTRICT");
                } else {
                    Schema::table($tabla, function (Blueprint $table) use ($columna, $destino) {
                        $table->dropForeign([$columna]);
                        $table->foreign($columna)->references('id')->on($destino)->restrictOnDelete();
                    });
                }
            }
        }
        if (DB::getDriverName() === 'mysql') {
            foreach (['inventarios', 'insumos'] as $tabla) {
                DB::statement("ALTER TABLE $tabla ADD CONSTRAINT {$tabla}_saldo_no_negativo CHECK (cantidad_actual >= 0)");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['inventarios', 'insumos'] as $tabla) {
                DB::statement("ALTER TABLE $tabla DROP CHECK {$tabla}_saldo_no_negativo");
            }
        }
        // Las referencias RESTRICT se conservan al revertir para no debilitar la protección histórica.
    }
};
