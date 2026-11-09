<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('configuraciones_envasado', function (Blueprint $t) {
            $t->id();
            $t->foreignId('presentacion_producto_id')->constrained('presentaciones_producto')->restrictOnDelete();
            $t->string('nombre', 120);
            $t->boolean('estado')->default(true);
            $t->unsignedBigInteger('version')->default(0);
            $t->timestamps();
            $t->unique(['presentacion_producto_id', 'nombre'], 'configuracion_presentacion_nombre_unico');
        });
        Schema::create('materiales_envasado', function (Blueprint $t) {
            $t->id();
            $t->foreignId('configuracion_envasado_id')->constrained('configuraciones_envasado')->restrictOnDelete();
            $t->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $t->string('funcion', 30);
            $t->string('base_consumo', 15)->default('unidad');
            $t->unsignedSmallInteger('unidades_por_empaque')->default(0);
            $t->string('tipo_empaque', 50)->nullable();
            $t->decimal('cantidad_por_unidad', 14, 3);
            $t->boolean('estado')->default(true);
            $t->timestamps();
            $t->unique(['configuracion_envasado_id', 'insumo_id'], 'configuracion_insumo_unico');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('materiales_envasado');
        Schema::dropIfExists('configuraciones_envasado');
    }
};
