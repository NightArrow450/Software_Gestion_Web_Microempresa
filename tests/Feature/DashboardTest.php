<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public static function rolesOperativos(): array
    {
        return [['Operaciones Comerciales'], ['Producción/Reparto']];
    }

    #[DataProvider('rolesOperativos')]
    public function test_accesos_respetan_permisos_y_no_consultan_conteos_administrativos(string $nombre): void
    {
        $rol = Role::create(['nombre' => $nombre, 'es_sistema' => true]);
        $rol->permisos()->attach(Permiso::create(['nombre' => 'envasado.ver', 'descripcion' => 'Consultar envasados'])->id);
        $usuario = User::factory()->create(['role_id' => $rol->id]);
        $conteos = 0;
        DB::listen(function ($consulta) use (&$conteos): void {
            if (str_contains(strtolower($consulta->sql), 'count(*) as total')) {
                $conteos++;
            }
        });

        $this->actingAs($usuario)->get(route('dashboard'))->assertOk()
            ->assertSee('Gestión diaria')->assertSee(route('envasado.index'))
            ->assertDontSee(route('usuarios.index'))->assertDontSee(route('indicadores.index'))
            ->assertDontSee('Usuarios activos')->assertDontSee('En desarrollo')
            ->assertDontSee('Sprint')->assertDontSee('Operativo');

        $this->assertSame(0, $conteos);
    }

    public function test_administrador_ve_conteos_reales_y_accesos_autorizados(): void
    {
        $rol = Role::create(['nombre' => 'Administrador', 'es_sistema' => true]);
        foreach (['indicadores.ver', 'auditoria.ver', 'pedidos.ver'] as $nombre) {
            $rol->permisos()->attach(Permiso::create(['nombre' => $nombre, 'descripcion' => $nombre])->id);
        }
        $usuario = User::factory()->create(['role_id' => $rol->id]);
        User::factory()->create(['role_id' => $rol->id, 'status' => false]);

        $this->actingAs($usuario)->get(route('dashboard'))->assertOk()
            ->assertViewHas('resumenUsuarios', ['total' => 1, 'administradores' => 1, 'comerciales' => 0, 'produccion' => 0])
            ->assertSee(route('usuarios.index'))->assertSee(route('indicadores.index'))
            ->assertSee(route('auditoria.index'))->assertSee(route('cumplimiento.lista-preparacion'))
            ->assertDontSee('Sprint')->assertDontSee('En desarrollo')->assertDontSee('Operativo');
    }

    public function test_permiso_de_auditoria_no_sustituye_el_rol_administrador(): void
    {
        $usuario = User::factory()->create();
        $usuario->role->permisos()->attach(Permiso::create(['nombre' => 'auditoria.ver', 'descripcion' => 'Auditoría'])->id);

        $this->actingAs($usuario)->get(route('dashboard'))->assertOk()->assertDontSee(route('auditoria.index'));
        $this->get(route('auditoria.index'))->assertForbidden();
    }

    public function test_cabecera_de_indicadores_usa_su_titulo_especifico(): void
    {
        $usuario = User::factory()->create();
        $usuario->role->permisos()->attach(Permiso::create(['nombre' => 'indicadores.ver', 'descripcion' => 'Indicadores'])->id);

        $this->actingAs($usuario)->get(route('indicadores.index'))->assertOk()
            ->assertSeeInOrder(['<header', 'Indicadores y CSV', '</header>'], false);
    }
}
