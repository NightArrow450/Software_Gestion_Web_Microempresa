<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            $table->string('modelo_comercial', 20)->nullable();
        });
        Schema::table('pedido_detalles', function (Blueprint $table): void {
            $table->unsignedInteger('cantidad_espera')->default(0);
            $table->unsignedInteger('cantidad_fisica_ajustada')->nullable();
        });
        Schema::create('pedido_versiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->string('motivo', 1000);
            $table->json('datos');
            $table->timestamp('created_at');
            $table->unique(['pedido_id', 'numero']);
        });
        Schema::create('pedido_agrupaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_version_id')->constrained('pedido_versiones')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->unsignedInteger('capacidad');
            $table->unique(['pedido_version_id', 'numero']);
        });
        Schema::create('pedido_agrupacion_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agrupacion_id')->constrained('pedido_agrupaciones')->restrictOnDelete();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->unique(['agrupacion_id', 'pedido_detalle_id'], 'agrupacion_detalle_unico');
        });
        foreach (['pedido_versiones', 'pedido_agrupaciones', 'pedido_agrupacion_detalles'] as $tabla) {
            foreach (['UPDATE', 'DELETE'] as $evento) {
                $nombre = $tabla.'_inmutable_'.strtolower($evento);
                DB::unprepared(DB::getDriverName() === 'sqlite'
                    ? "CREATE TRIGGER $nombre BEFORE $evento ON $tabla BEGIN SELECT RAISE(ABORT, 'La versión histórica del pedido es inmutable'); END"
                    : "CREATE TRIGGER $nombre BEFORE $evento ON $tabla FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La versión histórica del pedido es inmutable'");
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('No se elimina automáticamente el historial comercial. Requiere un procedimiento de recuperación revisado.');
    }
};
