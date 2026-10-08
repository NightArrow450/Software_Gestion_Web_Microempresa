<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variantes_producto', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Tipo de variante
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | Aroma
            | Color
            |
            */

            $table->string('tipo', 50);

            /*
            |--------------------------------------------------------------------------
            | Valor de la variante
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | Lavanda
            | Fresa
            | Rosado
            | Negro
            |
            */

            $table->string('valor', 100);

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'producto_id',
                'tipo',
                'valor',
            ]);

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variantes_producto');
    }
};