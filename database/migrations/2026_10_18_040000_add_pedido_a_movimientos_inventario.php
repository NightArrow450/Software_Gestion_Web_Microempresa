<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            // NULO para todos los movimientos manuales antiguos y futuros.
            $table->foreignId('pedido_id')->nullable()
                ->constrained('pedidos')->restrictOnDelete();

            // Una única salida automática por pedido y SKU. Varios registros
            // manuales con pedido_id NULL continúan permitidos.
            $table->unique(
                ['pedido_id', 'presentacion_producto_id'],
                'movimientos_pedido_sku_unico'
            );
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropUnique('movimientos_pedido_sku_unico');
            $table->dropConstrainedForeignId('pedido_id');
        });
    }
};
