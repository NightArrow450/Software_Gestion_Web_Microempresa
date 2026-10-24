<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::comprobarEntorno();

        return ['nombre' => 'Pruebas '.fake()->unique()->uuid(), 'es_sistema' => false];
    }

    public static function comprobarEntorno(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Las fábricas de usuarios y roles solo pueden utilizarse en pruebas aisladas.');
        }
        $conexion = DB::connection();
        $base = $conexion->getDatabaseName();
        $aislada = ($conexion->getDriverName() === 'sqlite' && $base === ':memory:')
            || ($conexion->getDriverName() === 'mysql' && getenv('MYSQL_TEST_ENABLE') === '1'
                && preg_match('/\Adhacer_eco_limp_test_[a-z0-9_]+\z/', $base));
        if (! $aislada) {
            throw new LogicException('La fábrica requiere SQLite en memoria o una base MySQL independiente de pruebas.');
        }
    }
}
