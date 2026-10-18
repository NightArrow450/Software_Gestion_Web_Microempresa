<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->unsignedInteger('stock_minimo')->default(10);
        });
    }
    public function down(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->dropColumn('stock_minimo');
        });
    }
};
