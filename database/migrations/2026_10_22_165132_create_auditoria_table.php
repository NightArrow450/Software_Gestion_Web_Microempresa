<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('accion', 100)->index();
            $table->string('entidad', 80);
            $table->unsignedBigInteger('entidad_id');
            $table->json('detalle');
            $table->ipAddress('ip')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['entidad', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
