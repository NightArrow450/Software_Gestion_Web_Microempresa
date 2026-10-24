<?php

namespace Tests\MySql;

use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

#[Group('mysql-concurrencia')]
class ConcurrenciaInventarioTest extends PruebaInventarioMySql
{
    public function test_dos_pedidos_compiten_y_uno_falla_sin_saldo_negativo(): void
    {
        [$usuario, $presentacion] = $this->producto(2);
        $pedidos = [$this->pedido($usuario, $presentacion), $this->pedido($usuario, $presentacion)];
        $resultados = $this->simultaneas('presentaciones_producto', $presentacion->id, array_map(fn (Pedido $pedido): array => $this->operacion($usuario, '/cumplimiento/'.$pedido->id.'/despachar', []), $pedidos));
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(0, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
        $this->assertSame(1, MovimientoInventario::where('presentacion_producto_id', $presentacion->id)->count());
    }

    public function test_mismo_despacho_simultaneo_no_se_descuenta_dos_veces(): void
    {
        [$usuario, $presentacion] = $this->producto(10);
        $pedido = $this->pedido($usuario, $presentacion);
        $operacion = $this->operacion($usuario, '/cumplimiento/'.$pedido->id.'/despachar', []);
        $resultados = $this->simultaneas('presentaciones_producto', $presentacion->id, [$operacion, $operacion]);
        $this->assertSame([302, 302], array_column($resultados, 'estado'));
        $this->assertSame(8, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
        $this->assertSame(1, MovimientoInventario::where('pedido_id', $pedido->id)->count());
        $this->assertSame(1, $pedido->historialEstados()->count());
    }

    public function test_salidas_simultaneas_de_productos_nunca_dejan_negativos(): void
    {
        [$usuario, $presentacion] = $this->producto(10);
        $operaciones = array_map(fn (): array => $this->operacion($usuario, '/inventario/movimientos', ['presentacion_producto_id' => $presentacion->id, 'tipo' => 'salida', 'cantidad' => 7, 'motivo' => 'Salida simultánea de prueba']), [1, 2]);
        $resultados = $this->simultaneas('presentaciones_producto', $presentacion->id, $operaciones);
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(3, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
    }

    public function test_salidas_simultaneas_de_insumos_nunca_dejan_negativos(): void
    {
        $usuario = $this->usuario();
        $insumo = Insumo::create(['codigo' => 'I'.Str::ulid(), 'nombre' => 'Envase de prueba', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 10]);
        $operaciones = array_map(fn (): array => $this->operacion($usuario, '/inventario/movimientos', ['insumo_id' => $insumo->id, 'tipo' => 'salida', 'cantidad' => 7, 'motivo' => 'Salida simultánea de prueba']), [1, 2]);
        $resultados = $this->simultaneas('insumos', $insumo->id, $operaciones);
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame('3.000', $insumo->fresh()->cantidad_actual);
    }

    public function test_indices_y_check_rechazan_nulos_parciales_y_saldos_negativos(): void
    {
        [$usuario, $presentacion] = $this->producto(5);
        $datos = ['usuario_id' => $usuario->id, 'presentacion_producto_id' => $presentacion->id, 'tipo' => 'entrada', 'cantidad' => 1, 'stock_anterior' => 5, 'stock_nuevo' => 6, 'motivo' => 'Integridad MySQL', 'origen_tipo' => 'simulado', 'origen_id' => 'O'.Str::ulid()];
        MovimientoInventario::create($datos);
        MovimientoInventario::create(array_replace($datos, ['origen_id' => 'O'.Str::ulid()]));
        foreach ([[$datos, 1062], [array_replace($datos, ['origen_id' => null]), 1644], [array_replace($datos, ['origen_tipo' => null, 'origen_id' => null]), 1644]] as [$invalido, $codigoEsperado]) {
            try {
                DB::table('movimientos_inventario')->insert($invalido);
                $this->fail('La base debió rechazar el movimiento.');
            } catch (QueryException $excepcion) {
                $this->assertSame($codigoEsperado, $excepcion->errorInfo[1]);
            }
        }
        try {
            DB::table('inventarios')->where('presentacion_producto_id', $presentacion->id)->update(['cantidad_actual' => -1]);
            $this->fail('La base debió rechazar el saldo negativo.');
        } catch (QueryException $excepcion) {
            $this->assertContains($excepcion->errorInfo[1], [1264, 3819]);
        }
        $this->assertSame(5, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
    }

    public function test_check_de_insumos_y_restricciones_historicas_en_mysql(): void
    {
        $insumo = Insumo::create(['codigo' => 'K'.Str::ulid(), 'nombre' => 'Envase protegido', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 5]);
        try {
            DB::table('insumos')->where('id', $insumo->id)->update(['cantidad_actual' => -1]);
            $this->fail('El CHECK debió rechazar el saldo negativo.');
        } catch (QueryException) {
            $this->assertSame('5.000', $insumo->fresh()->cantidad_actual);
        }
        [$usuario, $presentacion] = $this->producto(5);
        $pedido = $this->pedido($usuario, $presentacion);
        $antes = $pedido->detalles()->firstOrFail()->toArray();
        try {
            DB::table('presentaciones_producto')->where('id', $presentacion->id)->delete();
            $this->fail('La FK debió conservar la presentación histórica.');
        } catch (QueryException) {
            $this->assertSame($antes, $pedido->detalles()->firstOrFail()->toArray());
        }
    }

    public function test_mismo_despacho_con_tokens_distintos_sigue_protegido(): void
    {
        [$usuario, $presentacion] = $this->producto(10);
        $pedido = $this->pedido($usuario, $presentacion);
        $operaciones = array_map(fn (): array => $this->operacion($usuario, '/cumplimiento/'.$pedido->id.'/despachar', []), [1, 2]);
        $resultados = $this->simultaneas('presentaciones_producto', $presentacion->id, $operaciones);
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(8, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
        $this->assertSame(1, MovimientoInventario::where('pedido_id', $pedido->id)->count());
    }
}
