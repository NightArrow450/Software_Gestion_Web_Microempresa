<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencia_entrega_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias_entrega')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->text('motivo');
            $table->json('datos');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['incidencia_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia_entrega_eventos');
    }
};
