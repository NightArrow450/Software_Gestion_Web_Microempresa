<?php

namespace Database\Factories;

use App\Models\EnvasadoCorreccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EnvasadoCorreccion> */
class EnvasadoCorreccionFactory extends Factory
{
    protected $model = EnvasadoCorreccion::class;

    public function definition(): array
    {
        return ['clasificacion' => 'error_administrativo', 'cantidad' => 1, 'motivo' => 'Corrección de digitación de prueba'];
    }
}
