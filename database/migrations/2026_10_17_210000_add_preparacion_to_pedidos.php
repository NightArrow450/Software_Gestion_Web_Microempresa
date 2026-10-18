<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('preparacion_iniciada_por_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('preparacion_iniciada_en')->nullable();
            $table->foreignId('preparacion_finalizada_por_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('preparacion_finalizada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preparacion_iniciada_por_id');
            $table->dropConstrainedForeignId('preparacion_finalizada_por_id');
            $table->dropColumn(['preparacion_iniciada_en','preparacion_finalizada_en']);
        });
    }
};
