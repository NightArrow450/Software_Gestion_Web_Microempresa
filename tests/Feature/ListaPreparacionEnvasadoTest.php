<?php

namespace Tests\Feature;

use App\Models\Envasado;
use App\Models\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class ListaPreparacionEnvasadoTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    private function fila(): array
    {
        return $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->viewData('filas')->first();
    }

    public function test_agrupa_producto_completo_y_ordena_capacidad_con_unidades_compatibles(): void
    {
        $e = $this->escenarioEnvasado();
        $e['presentacion']->update(['contenido' => 3.8]);
        $this->pedidoEnvasado($e);
        $otro = $this->escenarioEnvasado();
        $this->pedidoEnvasado($otro);
        foreach ([[1, 'L'], [500, 'ml']] as [$contenido, $unidad]) {
            $presentacion = $e['presentacion']->replicate();
            $presentacion->codigo_sku .= '-'.$contenido;
            $presentacion->contenido = $contenido;
            $presentacion->unidad_medida = $unidad;
            $presentacion->save();
            $this->pedidoEnvasado(array_replace($e, ['presentacion' => $presentacion]));
        }
        $respuesta = $this->actingAs($e['usuario'])->get(route('cumplimiento.lista-preparacion'))->assertOk();
        $this->assertSame(['500 ml', '1 L', '3.8 L', '1 L'], $respuesta->viewData('filas')->pluck('presentacion')->all());
        $this->assertSame([5, 5, 5, 5], $respuesta->viewData('filas')->pluck('total_unidades')->all());
    }

    public function test_produccion_general_y_vinculada_neta_del_periodo_no_se_suma_al_stock(): void
    {
        $this->travelTo(now('America/Lima')->setDate(2026, 10, 23)->startOfDay());
        $e = $this->escenarioEnvasado(100, 20);
        $pedido = $this->pedidoEnvasado($e);
        $base = ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'usuario_id' => $e['usuario']->id];
        Envasado::factory()->create($base + ['cantidad' => 12, 'unidades_revertidas' => 2, 'created_at' => '2026-10-23 00:00:00']);
        Envasado::factory()->create($base + ['pedido_id' => $pedido->id, 'cantidad' => 5, 'unidades_revertidas' => 1, 'created_at' => '2026-10-23 23:59:59']);
        Envasado::factory()->create($base + ['cantidad' => 90, 'created_at' => '2026-10-22 23:59:59']);
        Envasado::factory()->create($base + ['cantidad' => 80, 'created_at' => '2026-10-24 00:00:00']);
        $this->actingAs($e['usuario']);
        $fila = $this->fila();
        $this->assertSame(14, $fila['envasadas']);
        $this->assertSame(4, $fila['vinculadas']);
        $this->assertSame(20, $fila['stock_disponible']);
        $this->assertSame(0, $fila['faltante']);
        $this->assertSame('Cubierta según stock', $fila['cobertura']);
        $this->assertDatabaseCount('envasados', 4);
    }

    public function test_stock_suficiente_no_inventa_produccion_y_nuevo_pedido_reabre_faltante(): void
    {
        $e = $this->escenarioEnvasado(100, 5);
        $this->pedidoEnvasado($e);
        $this->actingAs($e['usuario']);
        $this->assertSame('Cubierta según stock', $this->fila()['cobertura']);
        $this->assertSame(0, $this->fila()['envasadas']);
        $this->pedidoEnvasado($e);
        $fila = $this->fila();
        $this->assertSame(10, $fila['total_unidades']);
        $this->assertSame(5, $fila['faltante']);
        $this->assertSame('Parcialmente cubierta', $fila['cobertura']);
        $this->assertDatabaseCount('envasados', 0);
    }

    public function test_stock_de_otro_sku_no_cubre_faltante_consolidado(): void
    {
        $e = $this->escenarioEnvasado(100, 100);
        $this->pedidoEnvasado($e);
        $otra = $e['presentacion']->replicate();
        $otra->codigo_sku .= '-OTRO';
        $otra->save();
        $this->pedidoEnvasado(array_replace($e, ['presentacion' => $otra]));
        Inventario::create(['presentacion_producto_id' => $otra->id, 'cantidad_actual' => 0]);
        $this->actingAs($e['usuario']);
        $fila = $this->fila();
        $this->assertSame(100, $fila['stock_disponible']);
        $this->assertSame(5, $fila['faltante']);
        $this->assertSame('Parcialmente cubierta', $fila['cobertura']);
        $this->assertCount(2, $fila['presentaciones']);
    }

    public function test_necesidades_excluyen_estados_no_pendientes_y_respetan_fechas(): void
    {
        $e = $this->escenarioEnvasado();
        foreach (['confirmado', 'en_preparacion', 'listo_despacho', 'despachado', 'entregado', 'cancelado', 'registrado', 'observado'] as $estado) {
            $this->pedidoEnvasado($e, $estado);
        }
        $futuro = $this->pedidoEnvasado($e);
        $futuro->update(['fecha_entrega' => today()->addDays(2)]);
        $fila = $this->actingAs($e['usuario'])->get(route('cumplimiento.lista-preparacion', ['fecha_hasta' => today()->toDateString()]))->assertOk()->viewData('filas')->first();
        $this->assertSame(10, $fila['total_unidades']);
        $this->assertSame(10, $fila['faltante']);
        $this->assertSame('Pendiente', $fila['cobertura']);
    }

    public function test_periodos_invalidos_son_rechazados(): void
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario'])->get(route('cumplimiento.lista-preparacion', ['produccion_desde' => '2026-10-24', 'produccion_hasta' => '2026-10-23']))->assertSessionHasErrors('produccion_hasta');
        $this->get(route('cumplimiento.lista-preparacion', ['produccion_desde' => 'no-fecha']))->assertSessionHasErrors('produccion_desde');
    }
}
