<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['entrega_pedido_detalles' => ['entrega_id', 'entrega_linea_unica'], 'retorno_pedido_detalles' => ['retorno_id', 'retorno_linea_unica']] as $tabla => [$operacion, $anterior]) {
            Schema::table($tabla, function (Blueprint $t) use ($operacion): void {
                $t->unsignedBigInteger('despacho_componente_id')->nullable();
                $t->unsignedBigInteger('referencia_fisica')->virtualAs('COALESCE(despacho_componente_id, 0)');
                $t->unique([$operacion, 'pedido_detalle_id', 'referencia_fisica'], $operacion.'_fisica_unica');
            });
            Schema::table($tabla, fn (Blueprint $t) => $t->dropUnique($anterior));
            if (DB::getDriverName() === 'mysql') {
                Schema::table($tabla, fn (Blueprint $t) => $t->foreign(['despacho_componente_id', 'pedido_detalle_id'], $operacion.'_componente_fk')->references(['id', 'pedido_detalle_id'])->on('pedido_despacho_componentes')->restrictOnDelete());
            }
            $valida = "EXISTS (SELECT 1 FROM pedido_detalles d WHERE d.id = NEW.pedido_detalle_id AND ((d.tipo_linea = 'normal' AND NEW.despacho_componente_id IS NULL) OR (d.tipo_linea = 'surtido' AND EXISTS (SELECT 1 FROM pedido_despacho_componentes c WHERE c.id = NEW.despacho_componente_id AND c.pedido_detalle_id = d.id))))";
            foreach (['INSERT', 'UPDATE'] as $evento) {
                $nombre = $operacion.'_componente_'.strtolower($evento);
                if (DB::getDriverName() === 'sqlite') {
                    DB::unprepared("CREATE TRIGGER $nombre BEFORE $evento ON $tabla WHEN NOT ($valida) BEGIN SELECT RAISE(ABORT, 'Componente físico ajeno o ausente'); END");
                } else {
                    DB::unprepared("CREATE TRIGGER $nombre BEFORE $evento ON $tabla FOR EACH ROW BEGIN IF NOT ($valida) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Componente fisico ajeno o ausente'; END IF; END");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['entrega_pedido_detalles' => ['entrega_id', 'entrega_linea_unica'], 'retorno_pedido_detalles' => ['retorno_id', 'retorno_linea_unica']] as $tabla => [$operacion, $anterior]) {
            if (DB::table($tabla)->whereNotNull('despacho_componente_id')->exists()) {
                throw new RuntimeException('Hay resultados de surtidos: no puede retirarse su estructura sin perder trazabilidad.');
            }
        }
        foreach (['entrega_pedido_detalles' => ['entrega_id', 'entrega_linea_unica'], 'retorno_pedido_detalles' => ['retorno_id', 'retorno_linea_unica']] as $tabla => [$operacion, $anterior]) {
            foreach (['insert', 'update'] as $evento) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$operacion.'_componente_'.$evento);
            }
            Schema::table($tabla, function (Blueprint $t) use ($operacion, $anterior): void {
                if (DB::getDriverName() === 'mysql') {
                    $t->dropForeign($operacion.'_componente_fk');
                }
                $t->unique([$operacion, 'pedido_detalle_id'], $anterior);
                $t->dropUnique($operacion.'_fisica_unica');
                $t->dropColumn(['referencia_fisica', 'despacho_componente_id']);
            });
        }
    }
};
