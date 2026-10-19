<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicados = DB::table('clientes')->select('documento')
            ->whereNotNull('documento')->groupBy('documento')
            ->havingRaw('COUNT(*) > 1')->pluck('documento');

        if ($duplicados->isNotEmpty()) {
            $detalle = $duplicados->map(function (string $documento): string {
                $ids = DB::table('clientes')->where('documento', $documento)->orderBy('id')->pluck('id')->implode(', ');

                return json_encode($documento, JSON_UNESCAPED_UNICODE).' (clientes: '.$ids.')';
            })->implode('; ');

            throw new RuntimeException('No se creó el índice único: hay documentos duplicados. Revise estos registros sin borrarlos: '.$detalle);
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->unique('documento', 'clientes_documento_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique('clientes_documento_unique');
        });
    }
};
