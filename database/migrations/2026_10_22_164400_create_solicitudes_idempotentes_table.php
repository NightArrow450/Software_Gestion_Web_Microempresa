<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_idempotentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->char('token', 64);
            $table->char('huella', 64);
            $table->string('operacion');
            $table->text('redireccion')->nullable();
            $table->timestamp('created_at');
            $table->unique(['usuario_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_idempotentes');
    }
};
