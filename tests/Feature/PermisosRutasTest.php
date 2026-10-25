<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\RolesEscenario;
use Tests\TestCase;

class PermisosRutasTest extends TestCase
{
    use RefreshDatabase, RolesEscenario;

    public function test_cada_permiso_de_ruta_existe_y_el_administrador_lo_tiene(): void
    {
        $this->instalarRolesDePrueba();
        $admin = $this->rolDePrueba('Administrador');
        $menciones = [];
        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            foreach ($ruta->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'permiso:')) {
                    foreach (explode(',', substr($middleware, 8)) as $permiso) {
                        $menciones[$permiso] = true;
                    }
                }
            }
        }
        $this->assertNotEmpty($menciones);
        foreach (array_keys($menciones) as $permiso) {
            $this->assertTrue(Permiso::where('nombre', $permiso)->exists(), $permiso);
            $this->assertTrue($admin->tienePermiso($permiso), $permiso);
        }
    }

    public static function roles(): array
    {
        return [['Administrador', true], ['Operaciones Comerciales', false], ['Producción/Reparto', false]];
    }

    #[DataProvider('roles')]
    public function test_rutas_administrativas_respetan_el_rol_sin_ampliar_sus_permisos(string $nombre, bool $permitido): void
    {
        $this->instalarRolesDePrueba();
        $u = User::factory()->create(['role_id' => $this->rolDePrueba($nombre)->id]);
        $this->actingAs($u);
        foreach (['usuarios.index', 'roles.index', 'permisos.create', 'auditoria.index'] as $ruta) {
            $this->get(route($ruta))->assertStatus($permitido ? 200 : 403);
        }
    }

    public function test_cuenta_desactivada_con_sesion_abierta_se_expulsa_de_rutas_con_permiso(): void
    {
        $this->instalarRolesDePrueba();
        $u = User::factory()->create(['role_id' => $this->rolDePrueba('Operaciones Comerciales')->id]);
        $this->actingAs($u);
        $u->update(['status' => false]);

        $this->get(route('pedidos.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_seeders_conservan_matriz_de_los_cinco_permisos_de_envasado(): void
    {
        $this->instalarRolesDePrueba();
        $permisos = ['envasado.ver', 'envasado.registrar', 'envasado.corregir', 'envasado.reportar', 'materiales.envasado.configurar'];
        foreach (['Administrador' => [true, true, true, true, true], 'Operaciones Comerciales' => [true, false, false, true, true], 'Producción/Reparto' => [true, true, false, true, true]] as $nombre => $esperados) {
            $rol = $this->rolDePrueba($nombre);
            foreach ($permisos as $i => $permiso) {
                $this->assertSame($esperados[$i], $rol->tienePermiso($permiso), $nombre.' '.$permiso);
            }
        }
    }
}
