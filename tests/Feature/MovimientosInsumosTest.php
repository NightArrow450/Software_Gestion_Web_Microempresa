<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovimientosInsumosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create(['nombre' => 'Operaciones Comerciales', 'es_sistema' => true]);
        foreach ($permisos as $p) {
            $permiso = Permiso::firstOrCreate(['nombre' => $p], ['descripcion' => $p]);
            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'Prueba', 'last_name' => 'Insumos',
            'email' => 'movinsumos@local.test', 'password' => 'password123',
            'role_id' => $rol->id, 'status' => true,
        ]);
    }

    public function test_entrada_salida_y_ajuste_de_botellas_se_auditan(): void
    {
        $u = $this->usuario(['inventario.ver', 'inventario.entrada', 'inventario.salida', 'inventario.ajustar']);
        $insumo = Insumo::create([
            'codigo' => 'ENV-1L', 'nombre' => 'Botella 1 L', 'categoria' => 'Envases',
            'unidad_medida' => 'unidad', 'cantidad_actual' => '100.000',
        ]);
        foreach ([['entrada', 30], ['salida', 20], ['ajuste', 90]] as [$tipo, $cantidad]) {
            $this->actingAs($u)->post(route('inventario.movimientos.store'), [
                'insumo_id' => $insumo->id, 'tipo' => $tipo,
                'cantidad' => $cantidad, 'motivo' => 'Control de botellas de prueba',
            ])->assertRedirect();
        }
        $this->assertDatabaseHas('insumos', ['id' => $insumo->id, 'cantidad_actual' => '90.000']);
        $this->assertDatabaseCount('movimientos_inventario', 3);
        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $insumo->id, 'usuario_id' => $u->id,
            'tipo' => 'salida', 'stock_anterior' => '130.000', 'stock_nuevo' => '110.000',
        ]);
    }

    public function test_no_permite_retirar_mas_insumos_que_los_disponibles(): void
    {
        $u = $this->usuario(['inventario.ver', 'inventario.salida']);
        $insumo = Insumo::create([
            'codigo' => 'ENV-GAL', 'nombre' => 'Galón 3,5 L', 'categoria' => 'Envases',
            'unidad_medida' => 'unidad', 'cantidad_actual' => '3.000',
        ]);
        $this->actingAs($u)->post(route('inventario.movimientos.store'), [
            'insumo_id' => $insumo->id, 'tipo' => 'salida', 'cantidad' => 5,
            'motivo' => 'Consumo de envases',
        ])->assertSessionHasErrors('cantidad');
        $this->assertDatabaseHas('insumos', ['id' => $insumo->id, 'cantidad_actual' => '3.000']);
    }

    public function test_acepta_insumo_quimico_decimal_pero_exige_un_solo_destino(): void
    {
        $u = $this->usuario(['inventario.ver', 'inventario.entrada']);
        $insumo = Insumo::create([
            'codigo' => 'QUI-X', 'nombre' => 'Materia prima', 'categoria' => 'Químicos',
            'unidad_medida' => 'litro', 'cantidad_actual' => 0,
        ]);
        $this->actingAs($u)->post(route('inventario.movimientos.store'), [
            'insumo_id' => $insumo->id, 'tipo' => 'entrada',
            'cantidad' => '2.750', 'motivo' => 'Compra de materia prima',
        ])->assertRedirect();
        $this->assertDatabaseHas('insumos', ['id' => $insumo->id, 'cantidad_actual' => '2.750']);
        $this->actingAs($u)->post(route('inventario.movimientos.store'), [
            'tipo' => 'entrada', 'cantidad' => 5, 'motivo' => 'Sin destino de insumo',
        ])->assertSessionHasErrors('insumo_id');
    }

    public function test_ajuste_sin_motivo_y_usuario_sin_permiso_no_modifican_insumo(): void
    {
        $usuario = $this->usuario(['inventario.ver']);
        $insumo = Insumo::create(['codigo' => 'PROTEGIDO', 'nombre' => 'Contratapa', 'categoria' => 'Tapas', 'unidad_medida' => 'unidad', 'cantidad_actual' => 10]);
        $datos = ['insumo_id' => $insumo->id, 'tipo' => 'ajuste', 'cantidad' => 5];
        $this->actingAs($usuario)->post(route('inventario.movimientos.store'), $datos)->assertSessionHasErrors('motivo');
        $this->post(route('inventario.movimientos.store'), $datos + ['motivo' => 'Conteo físico de prueba'])->assertForbidden();
        $this->assertSame('10.000', $insumo->fresh()->cantidad_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('auditoria', 0);
    }

    public function test_historial_de_insumos_no_admite_actualizar_ni_eliminar(): void
    {
        $usuario = $this->usuario(['inventario.ver', 'inventario.entrada']);
        $insumo = Insumo::create(['codigo' => 'INMUTABLE', 'nombre' => 'Etiqueta', 'categoria' => 'Etiquetas', 'unidad_medida' => 'unidad', 'cantidad_actual' => 0]);
        $this->actingAs($usuario)->post(route('inventario.movimientos.store'), ['insumo_id' => $insumo->id, 'tipo' => 'entrada', 'cantidad' => 5, 'motivo' => 'Ingreso de etiquetas'])->assertRedirect();
        $movimiento = MovimientoInventario::firstOrFail();
        foreach (['actualizar', 'eliminar'] as $accion) {
            try {
                if ($accion === 'actualizar') {
                    $movimiento->update(['cantidad' => 999]);
                } else {
                    $movimiento->delete();
                }
                $this->fail('El historial debe ser inmutable.');
            } catch (\LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertSame('5.000', $movimiento->fresh()->cantidad);
        $this->assertDatabaseHas('auditoria', ['accion' => 'stock_entrada', 'entidad' => 'insumo', 'entidad_id' => $insumo->id]);
    }
}
