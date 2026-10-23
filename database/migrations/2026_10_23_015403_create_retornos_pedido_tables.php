<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retornos_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias_entrega')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('ocurrido_en');
            $table->text('observaciones');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('retorno_pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retorno_id')->constrained('retornos_pedido')->restrictOnDelete();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->restrictOnDelete();
            foreach (['aptas', 'danadas', 'en_revision', 'no_recuperadas', 'revisadas_aptas', 'revisadas_danadas', 'reincorporadas'] as $columna) {
                $table->unsignedBigInteger($columna)->default(0);
            }
            $table->unique(['retorno_id', 'pedido_detalle_id'], 'retorno_linea_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retorno_pedido_detalles');
        Schema::dropIfExists('retornos_pedido');
    }
};
