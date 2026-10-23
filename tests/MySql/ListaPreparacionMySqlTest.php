<?php

namespace Tests\MySql;

use App\Models\Envasado;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MaterialEnvasado;
use App\Models\MovimientoInventario;
use App\Services\TokenOperacion;
use Illuminate\Support\Str;
use Tests\EnvasadoEscenario;

class ListaPreparacionMySqlTest extends PruebaInventarioMySql
{
    use EnvasadoEscenario;

    private function datos(array $e, int $cantidad = 3): array
    {
        return ['desde_lista' => 1, 'fecha_desde' => '', 'fecha_hasta' => '',
            'produccion_desde' => today()->toDateString(), 'produccion_hasta' => today()->toDateString(),
            'presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id,
            'version_configuracion' => $e['configuracion']->version, 'cantidad' => $cantidad];
    }

    public function test_materiales_configurados_se_descuentan_exactamente_y_despacho_no_los_descuenta_de_nuevo(): void
    {
        $e = $this->escenarioEnvasado(100, 10);
        $e['configuracion']->materiales()->firstOrFail()->update(['cantidad_por_unidad' => 2]);
        $etiqueta = Insumo::create(['codigo' => 'ET'.Str::ulid(), 'nombre' => 'Etiqueta de prueba', 'categoria' => 'Etiquetas', 'unidad_medida' => 'unidad', 'cantidad_actual' => 50, 'estado' => true]);
        MaterialEnvasado::factory()->create(['configuracion_envasado_id' => $e['configuracion']->id, 'insumo_id' => $etiqueta->id, 'funcion' => 'etiqueta', 'cantidad_por_unidad' => 3]);
        $pedido = $this->pedidoEnvasado($e, 'en_preparacion');
        $datos = $this->datos($e) + ['pedido_id' => $pedido->id, '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->actingAs($e['usuario'])->post(route('envasado.previsualizar'), $datos)->assertOk();
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame('50.000', $etiqueta->fresh()->cantidad_actual);
        $this->post(route('envasado.store'), $datos)->assertRedirect();
        $this->post(route('envasado.store'), $datos)->assertRedirect();
        $this->assertSame('94.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame('41.000', $etiqueta->fresh()->cantidad_actual);
        $this->assertSame(13, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $this->assertSame(1, Envasado::where('presentacion_producto_id', $e['presentacion']->id)->count());
        $this->assertSame('en_preparacion', $pedido->fresh()->estado);
        $pedido->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertRedirect();
        $this->assertSame('despachado', $pedido->fresh()->estado);
        $this->assertSame(8, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $this->assertSame('94.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame('41.000', $etiqueta->fresh()->cantidad_actual);
        $this->assertSame(1, MovimientoInventario::where('pedido_id', $pedido->id)->where('tipo', 'salida')->count());
    }

    public function test_confirmaciones_simultaneas_desde_lista_con_mismo_token_producen_una_sola_entrada(): void
    {
        $e = $this->escenarioEnvasado();
        $op = $this->operacion($e['usuario'], '/envasados', $this->datos($e));
        $res = $this->simultaneas('presentaciones_producto', $e['presentacion']->id, [$op, $op]);
        $this->assertSame([302, 302], array_column($res, 'estado'));
        $this->assertSame('97.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(3, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $this->assertSame(1, Envasado::where('presentacion_producto_id', $e['presentacion']->id)->count());
        $this->assertSame(1, MovimientoInventario::where('presentacion_producto_id', $e['presentacion']->id)->where('tipo', 'entrada')->count());
    }

    public function test_parciales_simultaneos_del_mismo_pedido_se_acumulan_sin_reserva_ni_cambio_de_estado(): void
    {
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e);
        $ops = array_map(fn (int $cantidad): array => $this->operacion($e['usuario'], '/envasados', $this->datos($e, $cantidad) + ['pedido_id' => $pedido->id]), [3, 4]);
        $res = $this->simultaneas('presentaciones_producto', $e['presentacion']->id, $ops);
        $this->assertSame([302, 302], array_column($res, 'estado'));
        $this->assertSame('93.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(7, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $this->assertSame(2, Envasado::where('pedido_id', $pedido->id)->count());
        $this->assertSame('confirmado', $pedido->fresh()->estado);
    }
}
