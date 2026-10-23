<?php

namespace Database\Factories;

use App\Models\MaterialEnvasado;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaterialEnvasado> */
class MaterialEnvasadoFactory extends Factory
{
    protected $model = MaterialEnvasado::class;

    public function definition(): array
    {
        return ['funcion' => 'envase', 'base_consumo' => 'unidad', 'cantidad_por_unidad' => '1.000', 'estado' => true];
    }
}
