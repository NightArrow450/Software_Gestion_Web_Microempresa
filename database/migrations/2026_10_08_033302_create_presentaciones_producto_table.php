<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentaciones_producto', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Producto
            |--------------------------------------------------------------------------
            */

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Variante opcional
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            | Silicona Multiuso - Color Rosado
            |
            | Puede ser NULL si el producto no tiene variante.
            |
            */

            $table->foreignId('variante_producto_id')
                ->nullable()
                ->constrained('variantes_producto')
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Código único
            |--------------------------------------------------------------------------
            */

            $table->string('codigo_sku', 50)
                ->unique();


            /*
            |--------------------------------------------------------------------------
            | Presentación física
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | Bidón
            | Galón
            | Botella
            |
            */

            $table->string('tipo_envase', 60)
                ->nullable();


            /*
            | Ejemplos:
            | 20
            | 4
            | 1
            | 650
            | 330
            */

            $table->decimal('contenido', 10, 2);


            /*
            | Ejemplos:
            | L
            | ml
            | kg
            | g
            */

            $table->string('unidad_medida', 20);


            /*
            |--------------------------------------------------------------------------
            | Empaque comercial
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | Paquete
            | Caja
            |
            | La Lejía Concentrada 4.5% puede usar "Caja".
            | Los demás productos pueden usar "Paquete".
            |
            | Puede quedar NULL cuando se vende únicamente por unidad.
            |
            */

            $table->string('tipo_empaque', 30)
                ->nullable();


            /*
            | Cantidad de unidades individuales dentro
            | del paquete o caja.
            |
            | Ejemplo:
            | 650 ml x 15 unidades
            */

            $table->unsignedInteger('unidades_por_empaque')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Formas de venta
            |--------------------------------------------------------------------------
            */

            $table->boolean('venta_por_unidad')
                ->default(true);

            $table->boolean('venta_por_empaque')
                ->default(false);


            /*
            |--------------------------------------------------------------------------
            | Precios
            |--------------------------------------------------------------------------
            |
            | Pueden quedar NULL hasta ingresar los precios reales.
            |
            */

            $table->decimal('precio_unitario', 10, 2)
                ->nullable();

            $table->decimal('precio_empaque', 10, 2)
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            $table->boolean('estado')
                ->default(true);


            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index([
                'producto_id',
                'estado',
            ]);

            $table->index('variante_producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentaciones_producto');
    }
};