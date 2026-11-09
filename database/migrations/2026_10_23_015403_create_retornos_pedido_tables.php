<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $condicion = 'aptas >= 0 AND danadas >= 0 AND en_revision >= 0 AND no_recuperadas >= 0 AND revisadas_aptas >= 0 AND revisadas_danadas >= 0 AND reincorporadas >= 0 AND reincorporadas <= aptas + revisadas_aptas AND revisadas_aptas + revisadas_danadas <= en_revision';
        if (DB::getDriverName() === 'sqlite') {
            $expresion = preg_replace('/\\b(aptas|danadas|en_revision|no_recuperadas|revisadas_aptas|revisadas_danadas|reincorporadas)\\b/', 'NEW.$1', $condicion);
            foreach (['INSERT', 'UPDATE'] as $operacion) {
                DB::unprepared('CREATE TRIGGER retorno_cantidades_'.strtolower($operacion)." BEFORE $operacion ON retorno_pedido_detalles WHEN NOT ($expresion) BEGIN SELECT RAISE(ABORT, 'Cantidades de retorno inválidas'); END");
            }
        } else {
            DB::statement("ALTER TABLE retorno_pedido_detalles ADD CONSTRAINT retorno_cantidades_validas CHECK ($condicion)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retorno_pedido_detalles');
        Schema::dropIfExists('retornos_pedido');
    }
};
