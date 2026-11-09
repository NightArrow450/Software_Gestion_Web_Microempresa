<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('envasado_reportes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('envasado_id')->constrained('envasados')->restrictOnDelete();
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->text('motivo');
            $t->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('envasado_reportes');
    }
};
