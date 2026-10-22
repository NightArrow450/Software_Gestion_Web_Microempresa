<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $table) {
            $table->unsignedInteger('unidades_por_empaque')->nullable();
        });

        DB::transaction(function (): void {
            DB::table('pedido_detalles')->where('modalidad', 'empaque')
                ->whereNotNull('presentacion_producto_id')->orderBy('id')
                ->chunkById(500, function (Collection $detalles): void {
                    $tamanos = DB::table('presentaciones_producto')
                        ->whereIn('id', $detalles->pluck('presentacion_producto_id'))
                        ->pluck('unidades_por_empaque', 'id');

                    foreach ($detalles as $detalle) {
                        DB::table('pedido_detalles')->where('id', $detalle->id)
                            ->update(['unidades_por_empaque' => $tamanos[$detalle->presentacion_producto_id] ?? null]);
                    }
                });
        });
    }

    public function down(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $table) {
            $table->dropColumn('unidades_por_empaque');
        });
    }
};
