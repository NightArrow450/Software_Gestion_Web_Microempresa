<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('envasado_correcciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('envasado_id')->constrained('envasados')->restrictOnDelete();
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->string('clasificacion', 30);
            $t->unsignedInteger('cantidad');
            $t->text('motivo');
            $t->timestamps();
        });
        Schema::create('envasado_correccion_materiales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('envasado_correccion_id')->constrained('envasado_correcciones')->restrictOnDelete();
            $t->foreignId('envasado_consumo_id')->constrained('envasado_consumos')->restrictOnDelete();
            $t->decimal('cantidad_afectada', 14, 3);
            $t->decimal('cantidad_devuelta', 14, 3);
            $t->decimal('cantidad_perdida', 14, 3);
            $t->timestamps();
            $t->unique(['envasado_correccion_id', 'envasado_consumo_id'], 'correccion_consumo_unico');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('envasado_correccion_materiales');
        Schema::dropIfExists('envasado_correcciones');
    }
};
