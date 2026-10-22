<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder no está permitido en producción.');
        }

        Cliente::updateOrCreate(['nombre' => 'Cliente de demostración'], ['estado' => true]);
    }
}
