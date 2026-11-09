<?php

namespace Database\Factories;

use App\Models\EnvasadoConsumo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EnvasadoConsumo> */
class EnvasadoConsumoFactory extends Factory
{
    protected $model = EnvasadoConsumo::class;

    public function definition(): array
    {
        return ['nombre' => 'Envase de prueba', 'codigo' => 'ENV-PRUEBA', 'unidad_medida' => 'unidad', 'funcion' => 'envase', 'cantidad_por_unidad' => '1.000', 'cantidad' => '12.000'];
    }
}
