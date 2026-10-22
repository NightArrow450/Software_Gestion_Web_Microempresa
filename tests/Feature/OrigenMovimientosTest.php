<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrigenMovimientosTest extends TestCase
{
    use RefreshDatabase;

    private function datos(): array
    {
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'origen@test.local', 'password' => 'ClaveSegura123', 'status' => true, 'role_id' => Role::create(['nombre' => 'Pruebas', 'es_sistema' => false])->id]);
        $insumo = Insumo::create(['codigo' => 'ENV-1', 'nombre' => 'Envase', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 0]);

        return ['insumo_id' => $insumo->id, 'usuario_id' => $usuario->id, 'tipo' => 'entrada', 'cantidad' => 1, 'stock_anterior' => 0, 'stock_nuevo' => 1, 'motivo' => 'Ingreso de prueba'];
    }

    public function test_operaciones_distintas_y_movimientos_sin_pedido_permitidos(): void
    {
        $datos = $this->datos();
        foreach (['operacion-1', 'operacion-2'] as $origen) {
            MovimientoInventario::create($datos + ['origen_tipo' => 'prueba', 'origen_id' => $origen]);
        }
        MovimientoInventario::create($datos);
        MovimientoInventario::create(array_replace($datos, ['tipo' => 'ajuste']));
        $this->assertDatabaseCount('movimientos_inventario', 4);
        $this->assertSame(0, MovimientoInventario::whereNotNull('pedido_id')->count());
    }

    public function test_mismo_origen_y_destino_rechaza_duplicado(): void
    {
        $datos = $this->datos() + ['origen_tipo' => 'prueba', 'origen_id' => 'operacion-1'];
        MovimientoInventario::create($datos);
        $this->expectException(QueryException::class);
        MovimientoInventario::create($datos);
    }

    public function test_origen_parcial_no_elude_indice_unico(): void
    {
        $datos = $this->datos() + ['origen_tipo' => 'prueba', 'origen_id' => null];
        $this->expectException(QueryException::class);
        DB::table('movimientos_inventario')->insert($datos);
    }

    public function test_destino_nulo_no_elude_indice_unico(): void
    {
        $datos = array_replace($this->datos(), ['insumo_id' => null]);
        $this->expectException(QueryException::class);
        DB::table('movimientos_inventario')->insert($datos);
    }
}
