<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertasInsumosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create(['nombre' => 'Operaciones Comerciales', 'es_sistema' => true]);
        foreach ($permisos as $nombre) {
            $p = Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre]);
            $rol->permisos()->attach($p->id);
        }

        return User::create([
            'first_name' => 'Ana', 'last_name' => 'Almacén',
            'email' => 'alertasinsumos@local.test', 'password' => 'password123',
            'role_id' => $rol->id, 'status' => true,
        ]);
    }

    public function test_incluye_botellas_bajo_minimo_en_alertas(): void
    {
        config(['envasado.control_materiales_exacto' => true]);
        $u = $this->usuario(['inventario.ver']);
        Insumo::create([
            'codigo' => 'BOT-1L', 'nombre' => 'Botella 1 L', 'categoria' => 'Envases',
            'unidad_medida' => 'unidad', 'cantidad_actual' => 8, 'stock_minimo' => 20,
        ]);
        $this->actingAs($u)->get(route('inventario.stock', ['filtro' => 'bajo']))
            ->assertOk()->assertSee('BOT-1L')->assertSee('Stock bajo');
    }

    public function test_actualiza_minimo_de_insumo_sin_modificar_cantidad(): void
    {
        $u = $this->usuario(['inventario.ver', 'inventario.ajustar']);
        $i = Insumo::create([
            'codigo' => 'GAL-35', 'nombre' => 'Galón 3,5 L', 'categoria' => 'Envases',
            'unidad_medida' => 'unidad', 'cantidad_actual' => 12, 'stock_minimo' => 10,
        ]);
        $this->actingAs($u)->patch(route('inventario.insumos.stock.minimo', $i), [
            'stock_minimo' => 30,
        ])->assertRedirect();
        $this->assertDatabaseHas('insumos', [
            'id' => $i->id, 'cantidad_actual' => '12.000', 'stock_minimo' => '30.000',
        ]);
    }

    public function test_sin_permiso_no_puede_modificar_minimo_de_insumos(): void
    {
        $u = $this->usuario(['inventario.ver']);
        $i = Insumo::create([
            'codigo' => 'TAP-1L', 'nombre' => 'Tapa 1 L', 'categoria' => 'Tapas',
            'unidad_medida' => 'unidad', 'cantidad_actual' => 5, 'stock_minimo' => 10,
        ]);
        $this->actingAs($u)->patch(route('inventario.insumos.stock.minimo', $i), [
            'stock_minimo' => 99,
        ])->assertForbidden();
        $this->assertDatabaseHas('insumos', ['id' => $i->id, 'stock_minimo' => '10.000']);
    }
}
