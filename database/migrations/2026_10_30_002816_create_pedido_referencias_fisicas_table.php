<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_referencias_fisicas', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('id')->primary();
            $tabla->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->restrictOnDelete();
            $tabla->foreignId('presentacion_producto_id')->constrained('presentaciones_producto')->restrictOnDelete();
            $tabla->unsignedInteger('cantidad');
            $tabla->unsignedBigInteger('importe_centimos');
            $tabla->string('sku', 50);
            $tabla->string('descripcion', 255);
            $tabla->unsignedInteger('numero_paquete');
            $tabla->timestamp('created_at');
            $tabla->unique(['id', 'pedido_detalle_id'], 'referencia_fisica_linea_unica');
        });
        $columnas = ['id', 'pedido_detalle_id', 'presentacion_producto_id', 'cantidad', 'importe_centimos', 'sku', 'descripcion', 'numero_paquete', 'created_at'];
        if (Schema::hasTable('pedido_despacho_componentes')) {
            DB::table('pedido_despacho_componentes')->select($columnas)->orderBy('id')->chunkById(100, function ($filas): void {
                DB::table('pedido_referencias_fisicas')->insert($filas->map(fn ($fila): array => (array) $fila)->all());
            });
            if (DB::table('pedido_despacho_componentes')->count() !== DB::table('pedido_referencias_fisicas')->count()) {
                throw new RuntimeException('No coincide la fotografía física histórica; detener la actualización sin retirar tablas.');
            }
        }
        foreach (['entrega_pedido_detalles' => 'entrega_id', 'retorno_pedido_detalles' => 'retorno_id'] as $tabla => $operacion) {
            foreach (['insert', 'update'] as $evento) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$operacion.'_componente_'.$evento);
                DB::unprepared('DROP TRIGGER IF EXISTS '.$operacion.'_referencia_'.$evento);
            }
            $this->preservarTriggersSqlite($tabla, function () use ($tabla, $operacion): void {
                $nuevaReferencia = false;
                foreach (Schema::getForeignKeys($tabla) as $foreign) {
                    if ($foreign['foreign_table'] === 'pedido_despacho_componentes') {
                        Schema::table($tabla, fn (Blueprint $t) => $t->dropForeign($foreign['name'] ?? $foreign['columns']));
                    }
                    if ($foreign['foreign_table'] === 'pedido_referencias_fisicas') {
                        $nuevaReferencia = true;
                    }
                }
                if (! $nuevaReferencia) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->foreign(['despacho_componente_id', 'pedido_detalle_id'], $operacion.'_referencia_fk')
                        ->references(['id', 'pedido_detalle_id'])->on('pedido_referencias_fisicas')->restrictOnDelete());
                }
            });
            $valida = 'EXISTS (SELECT 1 FROM pedido_detalles d WHERE d.id = NEW.pedido_detalle_id AND ((NEW.despacho_componente_id IS NULL AND (d.presentacion_producto_id IS NOT NULL OR d.tipo_linea IS NULL OR d.tipo_linea = \'normal\')) OR EXISTS (SELECT 1 FROM pedido_referencias_fisicas r WHERE r.id = NEW.despacho_componente_id AND r.pedido_detalle_id = d.id)))';
            foreach (['INSERT', 'UPDATE'] as $evento) {
                $nombre = $operacion.'_referencia_'.strtolower($evento);
                DB::unprepared(DB::getDriverName() === 'mysql'
                    ? "CREATE TRIGGER $nombre BEFORE $evento ON $tabla FOR EACH ROW BEGIN IF NOT ($valida) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Referencia fisica ajena o ausente'; END IF; END"
                    : "CREATE TRIGGER $nombre BEFORE $evento ON $tabla WHEN NOT ($valida) BEGIN SELECT RAISE(ABORT, 'Referencia física ajena o ausente'); END");
            }
        }
        foreach (['UPDATE', 'DELETE'] as $evento) {

            $nombre = 'referencia_fisica_inmutable_'.strtolower($evento);
            if (DB::getDriverName() === 'mysql') {
                DB::unprepared("CREATE TRIGGER $nombre BEFORE $evento ON pedido_referencias_fisicas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La referencia fisica historica es inmutable'");
            } else {
                DB::unprepared("CREATE TRIGGER $nombre BEFORE $evento ON pedido_referencias_fisicas BEGIN SELECT RAISE(ABORT, 'La referencia física histórica es inmutable'); END");
            }
        }
    }

    private function preservarTriggersSqlite(string $tabla, Closure $cambio): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $cambio();

            return;
        }
        $triggers = DB::table('sqlite_master')->where('type', 'trigger')->where(fn ($q) => $q->where('tbl_name', $tabla)->orWhereRaw('instr(lower(sql), ?) > 0', [strtolower($tabla)]))->get(['name', 'sql']);
        foreach ($triggers as $trigger) {
            if (! is_string($trigger->sql) || ! str_starts_with($trigger->sql, 'CREATE TRIGGER') || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $trigger->name)) {
                throw new RuntimeException('Definición de trigger inesperada. Detener la migración sin reintentos automáticos.');
            }
        }
        foreach ($triggers as $trigger) {
            DB::unprepared('DROP TRIGGER '.$trigger->name);
        }
        $cambio();
        foreach ($triggers as $trigger) {
            if (DB::connection()->getPdo()->exec($trigger->sql) === false) {
                throw new RuntimeException('No se restableció un trigger de integridad. Detener la actualización.');
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La fotografía histórica no se retira mediante rollback automático.');
    }
};
