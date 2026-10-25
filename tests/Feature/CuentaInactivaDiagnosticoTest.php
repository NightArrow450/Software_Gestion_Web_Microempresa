<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\RolesEscenario;
use Tests\TestCase;

/** Regresión permanente de D9A-01, trasladada sin eliminar su reproducción. */
class CuentaInactivaDiagnosticoTest extends TestCase
{
    use RefreshDatabase, RolesEscenario;

    public function test_cuenta_desactivada_con_sesion_no_debe_acceder_al_dashboard(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->instalarRolesDePrueba();
        $usuario = User::factory()->create(['role_id' => $this->rolDePrueba('Operaciones Comerciales')->id]);
        $this->actingAs($usuario);
        $usuario->update(['status' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
