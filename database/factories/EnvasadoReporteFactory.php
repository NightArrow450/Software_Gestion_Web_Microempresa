<?php

namespace Database\Factories;

use App\Models\EnvasadoReporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EnvasadoReporte> */
class EnvasadoReporteFactory extends Factory
{
    protected $model = EnvasadoReporte::class;

    public function definition(): array
    {
        return ['motivo' => 'Solicito revisar el registro de prueba'];
    }
}
