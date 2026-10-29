<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('movimientos_inventario')->where('origen_tipo', 'despacho_unidades')->exists()) {
            throw new RuntimeException('Ya existe el origen reservado para despachos por unidades. Revisa su procedencia antes de migrar; no se modificaron esos movimientos.');
        }
        Schema::create('pedido_preparaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version_comercial');
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('despachadas_base');
            $table->string('incidencia', 40)->nullable();
            $table->string('motivo', 1000);
            $table->timestamp('created_at');
            $table->index(['pedido_id', 'pedido_detalle_id', 'id']);
            $table->foreign(['pedido_id', 'version_comercial'], 'preparacion_version_comercial_fk')->references(['pedido_id', 'numero'])->on('pedido_versiones')->restrictOnDelete();
        });
        Schema::create('pedido_despachos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version_comercial');
            $table->string('operacion', 64)->unique();
            $table->string('huella', 64);
            $table->string('motivo', 1000);
            $table->timestamp('created_at');
            $table->foreign(['pedido_id', 'version_comercial'], 'despacho_version_comercial_fk')->references(['pedido_id', 'numero'])->on('pedido_versiones')->restrictOnDelete();
        });
        Schema::create('pedido_despacho_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('despacho_id')->constrained('pedido_despachos')->restrictOnDelete();
            $table->foreignId('detalle_comercial_id')->constrained('pedido_detalles')->restrictOnDelete();
            $table->foreignId('detalle_fisico_id')->unique()->constrained('pedido_detalles')->restrictOnDelete();
            $table->foreignId('presentacion_producto_id')->constrained('presentaciones_producto')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->unique(['despacho_id', 'detalle_comercial_id']);
        });
        $triggers = DB::getDriverName() === 'sqlite' ? DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = 'movimientos_inventario'") : [];
        Schema::table('movimientos_inventario', function (Blueprint $table): void {
            $table->foreignId('despacho_unidades_id')->nullable()->constrained('pedido_despachos')->restrictOnDelete();
            $table->unique(['despacho_unidades_id', 'presentacion_producto_id'], 'movimientos_despacho_unidades_sku_unico');
        });
        foreach ($triggers as $trigger) {
            if (! DB::table('sqlite_master')->where('type', 'trigger')->where('name', $trigger->name)->exists()) {
                DB::connection()->getPdo()->exec($trigger->sql);
            }
        }
        $condicion = "(despacho_unidades_id IS NULL OR (origen_tipo IS NOT NULL AND origen_id IS NOT NULL AND origen_tipo = 'despacho_unidades' AND origen_id = CAST(despacho_unidades_id AS CHAR) AND pedido_id IS NULL AND presentacion_producto_id IS NOT NULL AND insumo_id IS NULL AND tipo = 'salida' AND cantidad > 0 AND cantidad = FLOOR(cantidad) AND stock_anterior - stock_nuevo = cantidad)) AND (origen_tipo IS NULL OR origen_tipo <> 'despacho_unidades' OR despacho_unidades_id IS NOT NULL)";
        if (DB::getDriverName() === 'mysql') {
            $condicion = str_replace('origen_id = CAST(despacho_unidades_id AS CHAR)', 'CAST(origen_id AS BINARY) = CAST(CAST(despacho_unidades_id AS CHAR) AS BINARY)', $condicion);
            DB::statement("ALTER TABLE movimientos_inventario ADD CONSTRAINT movimiento_despacho_unidades_valido CHECK ($condicion)");
        } else {
            $condicion = str_replace('FLOOR(cantidad)', 'CAST(cantidad AS INTEGER)', $condicion);
            $expresion = preg_replace('/\b(despacho_unidades_id|origen_tipo|origen_id|pedido_id|presentacion_producto_id|insumo_id|tipo|cantidad|stock_anterior|stock_nuevo)\b/', 'NEW.$1', $condicion);
            foreach (['INSERT', 'UPDATE'] as $evento) {
                $nombre = 'movimiento_despacho_unidades_'.strtolower($evento);
                DB::unprepared("CREATE TRIGGER $nombre BEFORE $evento ON movimientos_inventario WHEN NOT ($expresion) BEGIN SELECT RAISE(ABORT, 'El origen no corresponde al despacho físico'); END");
            }
        }
        foreach (['pedido_preparaciones', 'pedido_despachos', 'pedido_despacho_lineas'] as $tabla) {
            foreach (['UPDATE', 'DELETE'] as $evento) {
                $nombre = $tabla.'_inmutable_'.strtolower($evento);
                DB::unprepared(DB::getDriverName() === 'sqlite'
                    ? "CREATE TRIGGER $nombre BEFORE $evento ON $tabla BEGIN SELECT RAISE(ABORT, 'El hecho físico registrado es inmutable'); END"
                    : "CREATE TRIGGER $nombre BEFORE $evento ON $tabla FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El hecho físico registrado es inmutable'");
            }
        }
        foreach (['UPDATE', 'DELETE'] as $evento) {
            $nombre = 'detalle_fisico_unidades_inmutable_'.strtolower($evento);
            $condicion = 'EXISTS (SELECT 1 FROM pedido_despacho_lineas WHERE detalle_fisico_id = OLD.id)';
            DB::unprepared(DB::getDriverName() === 'sqlite'
                ? "CREATE TRIGGER $nombre BEFORE $evento ON pedido_detalles WHEN $condicion BEGIN SELECT RAISE(ABORT, 'El detalle despachado es inmutable'); END"
                : "CREATE TRIGGER $nombre BEFORE $evento ON pedido_detalles FOR EACH ROW BEGIN IF $condicion THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El detalle despachado es inmutable'; END IF; END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('No se eliminan despachos ni preparaciones históricas automáticamente.');
    }
};
