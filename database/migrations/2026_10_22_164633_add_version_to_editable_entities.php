<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pedidos', 'clientes', 'productos'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->unsignedBigInteger('version')->default(0));
        }
    }

    public function down(): void
    {
        foreach (['pedidos', 'clientes', 'productos'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('version'));
        }
    }
};
