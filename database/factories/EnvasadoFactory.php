<?php

namespace Database\Factories;

use App\Models\Envasado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Envasado> */
class EnvasadoFactory extends Factory
{
    protected $model = Envasado::class;

    public function definition(): array
    {
        return ['numero' => (string) Str::ulid(), 'cantidad' => 12, 'unidades_revertidas' => 0, 'estado' => 'registrado', 'presentacion_historica' => ['sku' => 'PRUEBA'], 'configuracion_nombre' => 'Alternativa de prueba'];
    }
}
