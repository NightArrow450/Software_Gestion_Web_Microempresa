<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_paquetes_surtidos', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->restrictOnDelete();
            $t->unsignedInteger('numero');
            $t->unsignedInteger('version')->default(0);
            $t->boolean('activo')->default(true);
            $t->unique(['pedido_detalle_id', 'numero'], 'paquete_surtido_numero_unico');
        });
        Schema::create('paquete_surtido_composiciones', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('paquete_surtido_id')->constrained('pedido_paquetes_surtidos')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('estado', 15);
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->text('motivo');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['paquete_surtido_id', 'version'], 'composicion_version_unica');
        });
        Schema::create('paquete_surtido_componentes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('composicion_id')->constrained('paquete_surtido_composiciones')->restrictOnDelete();
            $t->foreignId('presentacion_producto_id')->constrained('presentaciones_producto')->restrictOnDelete();
            $t->unsignedInteger('cantidad');
            $t->string('sku', 50);
            $t->string('descripcion', 255);
            $t->unique(['composicion_id', 'presentacion_producto_id'], 'composicion_sku_unico');
        });
        Schema::create('pedido_despacho_componentes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->restrictOnDelete();
            $t->foreignId('paquete_surtido_id')->constrained('pedido_paquetes_surtidos')->restrictOnDelete();
            $t->foreignId('componente_id')->unique()->constrained('paquete_surtido_componentes')->restrictOnDelete();
            $t->foreignId('presentacion_producto_id')->constrained('presentaciones_producto')->restrictOnDelete();
            $t->unsignedInteger('cantidad');
            $t->unsignedBigInteger('importe_centimos');
            $t->string('sku', 50);
            $t->string('descripcion', 255);
            $t->unsignedInteger('numero_paquete');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['id', 'pedido_detalle_id'], 'despacho_componente_linea_unico');
            $t->unique(['paquete_surtido_id', 'presentacion_producto_id'], 'despacho_paquete_sku_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_despacho_componentes');
        Schema::dropIfExists('paquete_surtido_componentes');
        Schema::dropIfExists('paquete_surtido_composiciones');
        Schema::dropIfExists('pedido_paquetes_surtidos');
    }
};
