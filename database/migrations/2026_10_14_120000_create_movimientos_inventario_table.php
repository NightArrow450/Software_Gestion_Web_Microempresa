<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentacion_producto_id')->nullable()
                ->constrained('presentaciones_producto')->restrictOnDelete();
            $table->foreignId('insumo_id')->nullable()
                ->constrained('insumos')->restrictOnDelete();
            // El controlador exige SOLO una referencia (presentación o insumo).
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 15); // entrada, salida o ajuste (cantidad final)
            $table->decimal('cantidad', 14, 3); // diferencia absoluta
            $table->decimal('stock_anterior', 14, 3);
            $table->decimal('stock_nuevo', 14, 3);
            $table->text('motivo');
            $table->timestamps();
            $table->index(['presentacion_producto_id', 'created_at']);
            $table->index(['insumo_id', 'created_at']);
            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
