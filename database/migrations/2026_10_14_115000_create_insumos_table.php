<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migración ADITIVA: la tabla inventarios del HU-04 original permanece intacta.
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 160);
            $table->string('categoria', 30); // Envases, Tapas, Etiquetas, Químicos, Otros
            $table->string('unidad_medida', 20); // unidad, litro, kilogramo, metro, paquete
            $table->decimal('cantidad_actual', 14, 3)->default(0);
            $table->decimal('stock_minimo', 14, 3)->default(10);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->index(['categoria', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
