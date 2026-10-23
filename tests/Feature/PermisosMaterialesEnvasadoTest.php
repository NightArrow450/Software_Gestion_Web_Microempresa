<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\ConfiguracionEnvasado;
use App\Models\MaterialEnvasado;
use App\Models\MovimientoInventario;
use App\Models\Permiso;
use App\Models\Role;
use App\Services\RegistrarEnvasado;
use App\Services\TokenOperacion;
use Database\Seeders\EnvasadoPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class PermisosMaterialesEnvasadoTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    public static function rolesAutorizados(): array
    {
        return ['administracion' => ['Administrador'], 'comerciales' => ['Operaciones Comerciales'], 'produccion' => ['Producción/Reparto']];
    }

    private function prepararRol(string $nombre): array
    {
        $e = $this->escenarioEnvasado();
        foreach (['Administrador', 'Operaciones Comerciales', 'Producción/Reparto'] as $rol) {
            Role::create(['nombre' => $rol, 'es_sistema' => true]);
        }
        $this->seed(EnvasadoPermisoSeeder::class);
        $e['usuario']->update(['role_id' => Role::where('nombre', $nombre)->value('id')]);
        $e['usuario']->unsetRelation('role');
        $this->actingAs($e['usuario']);

        return $e;
    }

    private function datos(array $e): array
    {
        return ['nombre' => 'Alternativa configurada manualmente', 'estado' => 1, 'version' => 0, 'materiales' => [['insumo_id' => $e['insumo']->id, 'funcion' => 'envase', 'base_consumo' => 'unidad', 'cantidad_por_unidad' => '1.000']]];
    }

    #[DataProvider('rolesAutorizados')]
    public function test_los_tres_roles_consultan_crean_editan_desactivan_y_activan_con_actor_auditado(string $rol): void
    {
        $e = $this->prepararRol($rol);
        $this->get(route('envasado.materiales', $e['presentacion']))->assertOk();
        $datos = $this->datos($e);
        $this->post(route('envasado.materiales.store', $e['presentacion']), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $c = ConfiguracionEnvasado::where('nombre', $datos['nombre'])->firstOrFail();
        $datos['materiales'][0]['cantidad_por_unidad'] = '2.000';
        $this->put(route('envasado.materiales.update', [$e['presentacion'], $c]), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('envasado.materiales.estado', [$e['presentacion'], $c]), ['estado' => 0, 'version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($c->fresh()->estado);
        $this->patch(route('envasado.materiales.estado', [$e['presentacion'], $c]), ['estado' => 1, 'version' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($c->fresh()->estado);
        $registros = Auditoria::where('entidad', 'configuracion_envasado')->where('entidad_id', $c->id)->get();
        foreach (['configuracion_envasado_creada', 'configuracion_envasado_modificada', 'configuracion_envasado_desactivada', 'configuracion_envasado_activada'] as $accion) {
            $this->assertTrue($registros->contains('accion', $accion));
        }
        $this->assertSame([$e['usuario']->id], $registros->pluck('usuario_id')->unique()->all());
        $modificacion = $registros->firstWhere('accion', 'configuracion_envasado_modificada');
        $this->assertSame('1.000', $modificacion->detalle['campos']['antes']['materiales'][0]['cantidad_por_unidad']);
        $this->assertSame('2.000', $modificacion->detalle['campos']['despues']['materiales'][0]['cantidad_por_unidad']);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_seeder_idempotente_conserva_roles_y_otros_permisos_sin_cargar_recetas(): void
    {
        $e = $this->prepararRol('Operaciones Comerciales');
        $permiso = Permiso::where('nombre', 'inventario.entrada')->firstOrFail();
        foreach (Role::whereIn('nombre', ['Administrador', 'Operaciones Comerciales', 'Producción/Reparto'])->get() as $rol) {
            $rol->permisos()->attach($permiso->id);
        }
        $antes = ConfiguracionEnvasado::count();
        $materiales = MaterialEnvasado::count();
        $roles = Role::count();
        $password = $e['usuario']->password;
        $this->seed(EnvasadoPermisoSeeder::class);
        $this->seed(EnvasadoPermisoSeeder::class);
        foreach (['Administrador' => [true, true], 'Operaciones Comerciales' => [false, false], 'Producción/Reparto' => [true, false]] as $nombre => [$registrar,$corregir]) {
            $rol = Role::where('nombre', $nombre)->firstOrFail();
            $this->assertTrue($rol->tienePermiso('materiales.envasado.configurar'));
            $this->assertTrue($rol->tienePermiso('inventario.entrada'));
            $this->assertSame($registrar, $rol->tienePermiso('envasado.registrar'));
            $this->assertSame($corregir, $rol->tienePermiso('envasado.corregir'));
        }
        $this->assertSame($roles, Role::count());
        $this->assertDatabaseMissing('roles', ['nombre' => 'Preparación/Reparto']);
        $this->assertSame($antes, ConfiguracionEnvasado::count());
        $this->assertSame($materiales, MaterialEnvasado::count());
        $this->assertSame($password, $e['usuario']->fresh()->password);
    }

    public function test_sin_permiso_especifico_todas_las_rutas_de_materiales_devuelven_403(): void
    {
        $e = $this->prepararRol('Administrador');
        $rol = $e['usuario']->role;
        $rol->permisos()->detach(Permiso::where('nombre', 'materiales.envasado.configurar')->value('id'));
        $e['usuario']->unsetRelation('role');
        $this->get(route('envasado.materiales', $e['presentacion']))->assertForbidden();
        $this->post(route('envasado.materiales.store', $e['presentacion']), $this->datos($e))->assertForbidden();
        $this->put(route('envasado.materiales.update', [$e['presentacion'], $e['configuracion']]), $this->datos($e))->assertForbidden();
        $this->patch(route('envasado.materiales.estado', [$e['presentacion'], $e['configuracion']]), ['estado' => 0, 'version' => 0])->assertForbidden();
    }

    public function test_cambio_de_estado_obsoleto_o_de_presentacion_ajena_no_modifica_configuracion(): void
    {
        $e = $this->prepararRol('Producción/Reparto');
        $otro = $this->escenarioEnvasado();
        $this->patch(route('envasado.materiales.estado', [$otro['presentacion'], $e['configuracion']]), ['estado' => 0, 'version' => 0])->assertNotFound();
        $datos = ['estado' => 0, 'version' => 0, '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->patch(route('envasado.materiales.estado', [$e['presentacion'], $e['configuracion']]), $datos)->assertRedirect();
        $this->patch(route('envasado.materiales.estado', [$e['presentacion'], $e['configuracion']]), $datos)->assertRedirect();
        $this->assertSame(1, $e['configuracion']->fresh()->version);
        $this->patch(route('envasado.materiales.estado', [$e['presentacion'], $e['configuracion']]), ['estado' => 1, 'version' => 0])->assertSessionHasErrors('version');
        $this->assertFalse($e['configuracion']->fresh()->estado);
        $this->assertSame(1, Auditoria::where('accion', 'configuracion_envasado_desactivada')->count());
    }

    public function test_ediciones_y_cambios_de_estado_conservan_consumos_y_movimientos_historicos(): void
    {
        $e = $this->prepararRol('Producción/Reparto');
        $registro = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 12], $e['usuario']);
        $consumos = $registro->consumos()->get()->toJson();
        $movimientos = MovimientoInventario::orderBy('id')->get()->toJson();
        $datos = $this->datos($e);
        $datos['materiales'][0]['cantidad_por_unidad'] = '3.000';
        $this->put(route('envasado.materiales.update', [$e['presentacion'], $e['configuracion']]), $datos)->assertRedirect();
        $e['insumo']->update(['estado' => false]);
        $this->patch(route('envasado.materiales.estado', [$e['presentacion'], $e['configuracion']]), ['estado' => 0, 'version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($consumos, $registro->consumos()->get()->toJson());
        $this->assertSame($movimientos,MovimientoInventario::orderBy('id')->get()->toJson());
        $this->assertSame('88.000',$e['insumo']->fresh()->cantidad_actual);
    }
}
