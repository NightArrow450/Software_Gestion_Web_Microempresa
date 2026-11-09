<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $condiciones = [
        'formatos_surtidos' => 'unidades_por_empaque BETWEEN 2 AND 1000 AND precio_empaque > 0 AND precio_empaque <= 999999.99 AND contenido > 0',
        'pedido_paquetes_surtidos' => 'numero > 0 AND version >= 0',
        'paquete_surtido_composiciones' => "version > 0 AND estado IN ('pendiente', 'definida')",
        'paquete_surtido_componentes' => 'cantidad BETWEEN 1 AND 1000',
        'pedido_despacho_componentes' => 'cantidad BETWEEN 1 AND 1000 AND importe_centimos >= 0 AND numero_paquete > 0',
    ];

    public function up(): void
    {
        foreach ($this->condiciones as $tabla => $condicion) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE $tabla ADD CONSTRAINT {$tabla}_valido CHECK ($condicion)");
            } else {
                $expresion = preg_replace('/\b(unidades_por_empaque|precio_empaque|contenido|numero|version|estado|cantidad|importe_centimos|numero_paquete)\b/', 'NEW.$1', $condicion);
                foreach (['INSERT', 'UPDATE'] as $evento) {
                    DB::unprepared('CREATE TRIGGER '.$tabla.'_valido_'.strtolower($evento)." BEFORE $evento ON $tabla WHEN NOT ($expresion) BEGIN SELECT RAISE(ABORT, 'Datos de surtido inválidos'); END");
                }
            }
        }
        foreach (['paquete_surtido_composiciones', 'paquete_surtido_componentes', 'pedido_despacho_componentes'] as $tabla) {
            foreach (['UPDATE', 'DELETE'] as $evento) {
                $nombre = $tabla.'_inmutable_'.strtolower($evento);
                DB::unprepared(DB::getDriverName() === 'sqlite'
                    ? "CREATE TRIGGER $nombre BEFORE $evento ON $tabla BEGIN SELECT RAISE(ABORT, 'El historial de surtidos es inmutable'); END"
                    : "CREATE TRIGGER $nombre BEFORE $evento ON $tabla FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El historial de surtidos es inmutable'");
            }
        }
        $valida = 'EXISTS (SELECT 1 FROM paquete_surtido_componentes c JOIN paquete_surtido_composiciones v ON v.id = c.composicion_id JOIN pedido_paquetes_surtidos p ON p.id = v.paquete_surtido_id WHERE c.id = NEW.componente_id AND c.presentacion_producto_id = NEW.presentacion_producto_id AND c.cantidad = NEW.cantidad AND c.sku = NEW.sku AND c.descripcion = NEW.descripcion AND p.id = NEW.paquete_surtido_id AND p.pedido_detalle_id = NEW.pedido_detalle_id AND p.numero = NEW.numero_paquete AND p.activo = 1 AND v.version = p.version AND v.estado = \'definida\')';
        DB::unprepared(DB::getDriverName() === 'sqlite'
            ? "CREATE TRIGGER despacho_surtido_propietario BEFORE INSERT ON pedido_despacho_componentes WHEN NOT ($valida) BEGIN SELECT RAISE(ABORT, 'El componente no corresponde al paquete vigente'); END"
            : "CREATE TRIGGER despacho_surtido_propietario BEFORE INSERT ON pedido_despacho_componentes FOR EACH ROW BEGIN IF NOT ($valida) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El componente no corresponde al paquete vigente'; END IF; END");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS despacho_surtido_propietario');
        foreach ($this->condiciones as $tabla => $condicion) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE $tabla DROP CHECK {$tabla}_valido");
            } else {
                foreach (['insert', 'update'] as $evento) {
                    DB::unprepared('DROP TRIGGER IF EXISTS '.$tabla.'_valido_'.$evento);
                }
            }
        }
        foreach (['paquete_surtido_composiciones', 'paquete_surtido_componentes', 'pedido_despacho_componentes'] as $tabla) {
            foreach (['update', 'delete'] as $evento) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$tabla.'_inmutable_'.$evento);
            }
        }
    }
};
