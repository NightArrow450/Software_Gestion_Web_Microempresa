<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsumosInventarioTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create(['nombre' => 'Operaciones Comerciales', 'es_sistema' => true]);
        foreach ($permisos as $nombre) {
            $permiso = Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre]);
            $rol->permisos()->attach($permiso->id);
        }
        return User::create([
            'first_name' => 'Operador', 'last_name' => 'Insumos',
            'email' => 'insumos@local.test', 'password' => 'password123',
            'role_id' => $rol->id, 'status' => true,
        ]);
    }

    public function test_registra_galon_vacio_con_existencia_inicial(): void
    {
        $u = $this->usuario(['inventario.ver', 'inventario.ajustar']);
        $this->actingAs($u)->post(route('inventario.insumos.store'), [
            'codigo' => 'ENV-GAL-35L', 'nombre' => 'Galón vacío de 3,5 L',
            'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => '40',
        ])->assertRedirect(route('inventario.index'));
        $this->assertDatabaseHas('insumos', [
            'codigo' => 'ENV-GAL-35L', 'cantidad_actual' => '40.000',
        ]);
        $this->actingAs($u)->get(route('inventario.index'))
            ->assertOk()->assertSee('ENV-GAL-35L')->assertSee('Galón vacío');
    }

    public function test_puede_registrar_materia_prima_por_litros(): void
    {
        $u = $this->usuario(['inventario.ver', 'inventario.ajustar']);
        $this->actingAs($u)->post(route('inventario.insumos.store'), [
            'codigo' => 'QUI-BASE', 'nombre' => 'Base química', 'categoria' => 'Químicos',
            'unidad_medida' => 'litro', 'cantidad_actual' => '12.500',
        ])->assertRedirect();
        $this->assertDatabaseHas('insumos', ['codigo' => 'QUI-BASE', 'cantidad_actual' => '12.500']);
    }

    public function test_sin_permiso_de_ajuste_no_puede_registrar_insumos(): void
    {
        $u = $this->usuario(['inventario.ver']);
        $this->actingAs($u)->post(route('inventario.insumos.store'), [
            'codigo' => 'ENV-1L', 'nombre' => 'Botella 1 L', 'categoria' => 'Envases',
            'unidad_medida' => 'unidad', 'cantidad_actual' => 100,
        ])->assertForbidden();
        $this->assertDatabaseCount('insumos', 0);
    }
}
