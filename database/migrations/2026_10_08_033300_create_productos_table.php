<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 20)
                ->unique();

            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->string('nombre', 150);

            $table->string('marca', 100)
                ->nullable();

            $table->text('descripcion')
                ->nullable();

            $table->string('imagen_referencia')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->index('nombre');
            $table->index('marca');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};