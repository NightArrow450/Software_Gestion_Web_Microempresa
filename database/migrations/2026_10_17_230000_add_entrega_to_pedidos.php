<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('entregado_por_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('entregado_en')->nullable();
            $table->text('observacion_entrega')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entregado_por_id');
            $table->dropColumn(['entregado_en', 'observacion_entrega']);
        });
    }
};
