<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formatos_surtidos', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $t->string('nombre', 160);
            $t->string('tipo_envase', 80);
            $t->decimal('contenido', 10, 2);
            $t->string('unidad_medida', 20);
            $t->unsignedInteger('unidades_por_empaque');
            $t->decimal('precio_empaque', 12, 2);
            $t->boolean('estado')->default(true);
            $t->unsignedInteger('version')->default(0);
            $t->timestamps();
        });
        Schema::create('formato_surtido_skus', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('formato_surtido_id')->constrained('formatos_surtidos')->restrictOnDelete();
            $t->foreignId('presentacion_producto_id')->constrained('presentaciones_producto')->restrictOnDelete();
            $t->unique(['formato_surtido_id', 'presentacion_producto_id'], 'surtido_sku_unico');
        });
        Schema::table('pedido_detalles', function (Blueprint $t): void {
            $t->string('tipo_linea', 15)->default('normal');
            $t->foreignId('formato_surtido_id')->nullable()->constrained('formatos_surtidos')->restrictOnDelete();
            $t->timestamp('retirado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $t): void {
            $t->dropForeign(['formato_surtido_id']);
            $t->dropColumn(['formato_surtido_id', 'tipo_linea', 'retirado_en']);
        });
        Schema::dropIfExists('formato_surtido_skus');
        Schema::dropIfExists('formatos_surtidos');
    }
};
