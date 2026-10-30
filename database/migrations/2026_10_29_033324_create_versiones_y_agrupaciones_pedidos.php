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
            $table->unique(['id', 'pedido_id'], 'version_pedido_identidad_unica');
        });
        Schema::create('pedido_agrupaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_version_id')->constrained('pedido_versiones')->restrictOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->unsignedInteger('capacidad');
            $table->uuid('identidad');
            $table->unique(['pedido_version_id', 'numero']);
            $table->unique(['pedido_version_id', 'identidad'], 'version_agrupacion_identidad_unica');
            $table->unique(['id', 'pedido_id', 'identidad'], 'agrupacion_pedido_identidad_unica');
            $table->foreign(['pedido_version_id', 'pedido_id'], 'agrupacion_version_pedido_fk')->references(['id', 'pedido_id'])->on('pedido_versiones')->restrictOnDelete();
        });
        Schema::create('pedido_agrupacion_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agrupacion_id')->constrained('pedido_agrupaciones')->restrictOnDelete();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->unique(['agrupacion_id', 'pedido_detalle_id'], 'agrupacion_detalle_unico');
        });
        $coherente = "NEW.cantidad > 0 AND EXISTS (SELECT 1 FROM pedido_agrupaciones g JOIN pedido_detalles d ON d.pedido_id = g.pedido_id WHERE g.id = NEW.agrupacion_id AND d.id = NEW.pedido_detalle_id AND d.modalidad = 'unidad' AND d.tipo_linea = 'normal' AND d.retirado_en IS NULL)";
        DB::unprepared(DB::getDriverName() === 'sqlite'
            ? "CREATE TRIGGER agrupacion_detalle_coherente BEFORE INSERT ON pedido_agrupacion_detalles WHEN NOT ($coherente) BEGIN SELECT RAISE(ABORT, 'El componente no pertenece al pedido o no es una cantidad física válida'); END"
            : "CREATE TRIGGER agrupacion_detalle_coherente BEFORE INSERT ON pedido_agrupacion_detalles FOR EACH ROW BEGIN IF NOT ($coherente) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El componente no pertenece al pedido o no es una cantidad física válida'; END IF; END");
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
