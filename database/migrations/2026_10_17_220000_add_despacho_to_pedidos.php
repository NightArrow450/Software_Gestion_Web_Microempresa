<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('despachado_por_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('despachado_en')->nullable();
            $table->text('observacion_despacho')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('despachado_por_id');
            $table->dropColumn(['despachado_en', 'observacion_despacho']);
        });
    }
};
