
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 180);
            $table->string('documento', 20)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 180)->nullable();

            $table->string('mercado', 150)->nullable();
            $table->string('puesto_referencia', 180)->nullable();
            $table->string('distrito', 120)->nullable();
            $table->string('direccion', 255)->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();

            $table->index(['estado', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
