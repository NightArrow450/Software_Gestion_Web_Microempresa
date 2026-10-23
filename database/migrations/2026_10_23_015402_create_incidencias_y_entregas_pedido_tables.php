<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias_entrega', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos')->restrictOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->string('estado_revision', 20)->default('pendiente');
            $table->text('resolucion')->nullable();
            $table->foreignId('resuelto_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resuelto_en')->nullable();
            $table->timestamps();
        });
        Schema::create('entregas_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias_entrega')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 20)->default('resultado');
            $table->timestamp('ocurrido_en');
            $table->text('observaciones')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('entrega_pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('entregas_pedido')->restrictOnDelete();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->restrictOnDelete();
            $table->bigInteger('aceptadas');
            $table->bigInteger('rechazadas');
            $table->text('motivo')->nullable();
            $table->unique(['entrega_id', 'pedido_detalle_id'], 'entrega_linea_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_pedido_detalles');
        Schema::dropIfExists('entregas_pedido');
        Schema::dropIfExists('incidencias_entrega');
    }
};
