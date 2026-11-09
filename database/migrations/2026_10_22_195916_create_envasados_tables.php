<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('envasados', function (Blueprint $t) {
            $t->id();
            $t->char('numero', 26)->unique();
            $t->foreignId('presentacion_producto_id')->constrained('presentaciones_producto')->restrictOnDelete();
            $t->foreignId('configuracion_envasado_id')->constrained('configuraciones_envasado')->restrictOnDelete();
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->restrictOnDelete();
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('cantidad');
            $t->unsignedInteger('unidades_revertidas')->default(0);
            $t->string('estado', 20)->default('registrado');
            $t->json('presentacion_historica');
            $t->string('configuracion_nombre', 120);
            $t->text('observaciones')->nullable();
            $t->timestamps();
            $t->index(['pedido_id', 'presentacion_producto_id']);
        });
        Schema::create('envasado_consumos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('envasado_id')->constrained('envasados')->restrictOnDelete();
            $t->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $t->string('nombre', 160);
            $t->string('codigo', 50);
            $t->string('unidad_medida', 20);
            $t->string('funcion', 30);
            $t->decimal('cantidad_por_unidad', 14, 3);
            $t->decimal('cantidad', 14, 3);
            $t->timestamps();
            $t->unique(['envasado_id', 'insumo_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('envasado_consumos');
        Schema::dropIfExists('envasados');
    }
};
