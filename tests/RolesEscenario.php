<?php

namespace Tests;

use App\Models\Role;
use Database\Seeders\AuditoriaPermisoSeeder;
use Database\Seeders\EntregaRetornoPermisoSeeder;
use Database\Seeders\EnvasadoPermisoSeeder;
use Database\Seeders\IndicadoresPermisoSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\SurtidosPermisoSeeder;
use Illuminate\Support\Facades\DB;

trait RolesEscenario
{
    protected function instalarRolesDePrueba(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->seed([RoleSeeder::class, PermisoSeeder::class, RolPermisoSeeder::class, AuditoriaPermisoSeeder::class, EnvasadoPermisoSeeder::class, EntregaRetornoPermisoSeeder::class, SurtidosPermisoSeeder::class, IndicadoresPermisoSeeder::class]);
    }

    protected function rolDePrueba(string $nombre): Role
    {
        return Role::where('nombre', $nombre)->firstOrFail();
    }
}
