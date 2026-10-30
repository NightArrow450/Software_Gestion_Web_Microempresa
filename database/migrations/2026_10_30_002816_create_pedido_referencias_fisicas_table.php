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
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $evento) {
            if ($evento === 'INSERT') {
                continue;
            }
            $nombre = 'referencia_fisica_inmutable_'.strtolower($evento);
            if (DB::getDriverName() === 'mysql') {
                DB::unprepared("CREATE TRIGGER $nombre BEFORE $evento ON pedido_referencias_fisicas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La referencia fisica historica es inmutable'");
            } else {
                DB::unprepared("CREATE TRIGGER $nombre BEFORE $evento ON pedido_referencias_fisicas BEGIN SELECT RAISE(ABORT, 'La referencia física histórica es inmutable'); END");
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La fotografía histórica no se retira mediante rollback automático.');
    }
};
