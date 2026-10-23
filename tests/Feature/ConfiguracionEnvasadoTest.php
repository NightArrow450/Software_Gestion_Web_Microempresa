<?php

namespace Tests\Feature;

use App\Models\ConfiguracionEnvasado;
use App\Models\Insumo;
use App\Models\Permiso;
use App\Models\Role;
use App\Services\ConfigurarMaterialesEnvasado;
use Database\Seeders\EnvasadoPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class ConfiguracionEnvasadoTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    private function datos(array $e): array
    {
        return ['nombre' => 'Envase compatible', 'estado' => 1, 'version' => 0, 'materiales' => [['insumo_id' => $e['insumo']->id, 'funcion' => 'envase', 'base_consumo' => 'unidad', 'cantidad_por_unidad' => '1.000']]];
    }

    public function test_configurar_consultar_y_retirar_materiales_sin_borrar_historial(): void
    {
        $e = $this->escenarioEnvasado();
        $d = $this->datos($e);
        $this->actingAs($e['usuario'])->get(route('envasado.materiales', $e['presentacion']))->assertOk();
        $this->post(route('envasado.materiales.store', $e['presentacion']), $d)->assertRedirect();
        $c = ConfiguracionEnvasado::where('nombre', $d['nombre'])->firstOrFail();
        $otro = Insumo::create(['codigo' => 'ETQ', 'nombre' => 'Etiqueta', 'categoria' => 'Etiquetas', 'unidad_medida' => 'unidad', 'cantidad_actual' => 100, 'estado' => true]);
        $d['materiales'][0]['insumo_id'] = $otro->id;
        $this->put(route('envasado.materiales.update', [$e['presentacion'], $c]), $d)->assertRedirect();
        $this->assertDatabaseHas('materiales_envasado', ['configuracion_envasado_id' => $c->id, 'insumo_id' => $e['insumo']->id, 'estado' => false]);
        $this->assertSame(1, $c->fresh()->version);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
    }

    public function test_rechaza_inactivos_consumos_invalidos_y_duplicados(): void
    {
        foreach (['inactivo', 'negativo', 'precision', 'duplicado', 'inexistente', 'quimico'] as $caso) {
            $e = $this->escenarioEnvasado();
            $d = $this->datos($e);
            if ($caso === 'inactivo') {
                $e['insumo']->update(['estado' => false]);
            }
            if ($caso === 'negativo') {
                $d['materiales'][0]['cantidad_por_unidad'] = -1;
            }
            if ($caso === 'precision') {
                $d['materiales'][0]['cantidad_por_unidad'] = '0.0001';
            }
            if ($caso === 'duplicado') {
                $d['materiales'][] = $d['materiales'][0];
            }
            if ($caso === 'inexistente') {
                $d['materiales'][0]['insumo_id'] = 999999;
            }
            if ($caso === 'quimico') {
                $e['insumo']->update(['categoria' => 'Químicos']);
            }
            $this->actingAs($e['usuario'])->post(route('envasado.materiales.store', $e['presentacion']), $d)->assertSessionHasErrors();
        }
    }

    public function test_mangas_exigen_metros_tipo_y_tamano_de_empaque(): void
    {
        $e = $this->escenarioEnvasado();
        $e['insumo']->update(['unidad_medida' => 'metro', 'categoria' => 'Otros']);
        $d = $this->datos($e);
        $d['materiales'][0] = array_replace($d['materiales'][0], ['funcion' => 'manga', 'base_consumo' => 'empaque', 'cantidad_por_unidad' => '0.650', 'tipo_empaque' => 'Paquete', 'unidades_por_empaque' => 5]);
        $c = app(ConfigurarMaterialesEnvasado::class)->guardar($e['presentacion'], $d);
        $this->assertSame('0.650', $c->materiales->first()->cantidad_por_unidad);
        unset($d['materiales'][0]['tipo_empaque']);
        $d['nombre'] = 'Otra';
        $this->expectException(ValidationException::class);
        app(ConfigurarMaterialesEnvasado::class)->guardar($e['presentacion'], $d);
    }

    public function test_version_obsoleta_no_sobrescribe_configuracion(): void
    {
        $e = $this->escenarioEnvasado();
        $d = $this->datos($e);
        $servicio = app(ConfigurarMaterialesEnvasado::class);
        $servicio->guardar($e['presentacion'], $d, $e['configuracion']);
        $this->expectException(ValidationException::class);
        $servicio->guardar($e['presentacion'], $d, $e['configuracion']);
    }

    public function test_permisos_son_aditivos_idempotentes_y_no_otorgan_correccion_a_produccion(): void
    {
        foreach (['Administrador', 'Operaciones Comerciales', 'Producción/Reparto'] as $nombre) {
            Role::create(['nombre' => $nombre, 'es_sistema' => true]);
        }
        $e = $this->escenarioEnvasado();
        $antes = $e['usuario']->password;
        $this->seed(EnvasadoPermisoSeeder::class);
        $this->seed(EnvasadoPermisoSeeder::class);
        $this->assertSame(1, Permiso::where('nombre', 'envasado.corregir')->count());
        $this->assertFalse(Role::where('nombre', 'Producción/Reparto')->first()->tienePermiso('envasado.corregir'));
        $this->assertSame($antes, $e['usuario']->fresh()->password);
        $this->assertTrue($e['usuario']->role->tienePermiso('inventario.entrada'));
    }

    public function test_sin_permiso_no_se_configuran_materiales(): void
    {
        $e = $this->escenarioEnvasado();
        $e['usuario']->role->permisos()->detach();
        $this->actingAs($e['usuario'])->post(route('envasado.materiales.store', $e['presentacion']), $this->datos($e))->assertForbidden();
    }
}
