<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('fecha_pedido');
            $table->date('fecha_entrega');

            $table->string('condicion_pago', 20)
                ->default('contado');

            $table->string('estado', 30)
                ->default('registrado');

            $table->decimal('total', 12, 2)
                ->default(0);

            $table->text('observaciones')->nullable();

            // Copia de los datos del cliente para
            // preservar las notas históricas.
            $table->string('cliente_nombre', 180);
            $table->string('cliente_documento', 20)->nullable();
            $table->string('cliente_telefono', 30)->nullable();
            $table->string('cliente_mercado', 150)->nullable();
            $table->string('cliente_puesto_referencia', 180)->nullable();
            $table->string('cliente_distrito', 120)->nullable();
            $table->string('cliente_direccion', 255)->nullable();

            $table->timestamps();

            $table->index(['estado', 'fecha_entrega']);
        });

        Schema::create('pedido_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete();

            $table->foreignId('presentacion_producto_id')
                ->nullable()
                ->constrained('presentaciones_producto')
                ->nullOnDelete();

            $table->string('codigo_sku', 50);
            $table->string('descripcion_producto', 255);

            $table->string('modalidad', 20);

            $table->unsignedInteger('cantidad');

            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('importe', 12, 2);

            $table->timestamps();
        });

        Schema::create('pedido_estados_historial', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->text('motivo')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['pedido_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_estados_historial');
        Schema::dropIfExists('pedido_detalles');
        Schema::dropIfExists('pedidos');
    }
};
