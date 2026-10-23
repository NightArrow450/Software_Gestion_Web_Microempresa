<?php

namespace Database\Factories;

use App\Models\EnvasadoCorreccionMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EnvasadoCorreccionMaterial> */
class EnvasadoCorreccionMaterialFactory extends Factory
{
    protected $model = EnvasadoCorreccionMaterial::class;

    public function definition(): array
    {
        return ['cantidad_afectada' => '1.000', 'cantidad_devuelta' => '1.000', 'cantidad_perdida' => '0.000'];
    }
}
