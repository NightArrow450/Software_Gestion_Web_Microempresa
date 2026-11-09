<?php

namespace Database\Factories;

use App\Models\ConfiguracionEnvasado;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConfiguracionEnvasado> */
class ConfiguracionEnvasadoFactory extends Factory
{
    protected $model = ConfiguracionEnvasado::class;

    public function definition(): array
    {
        return ['nombre' => fake()->unique()->words(3, true), 'estado' => true, 'version' => 0];
    }
}
