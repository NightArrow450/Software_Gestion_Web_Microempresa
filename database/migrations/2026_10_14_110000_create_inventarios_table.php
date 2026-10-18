<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            // Un registro de existencia por presentación / SKU, no por producto.
            $table->foreignId('presentacion_producto_id')->unique()
                ->constrained('presentaciones_producto')->restrictOnDelete();
            $table->unsignedInteger('cantidad_actual')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
