<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('pedidos', 'procesado_por_id')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->foreignId('procesado_por_id')
                    ->nullable()->constrained('users')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('pedidos', 'procesado_en')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->timestamp('procesado_en')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pedidos', 'procesado_en')) {
            Schema::table('pedidos', fn (Blueprint $table) => $table->dropColumn('procesado_en'));
        }
        if (Schema::hasColumn('pedidos', 'procesado_por_id')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('procesado_por_id');
            });
        }
    }
};
