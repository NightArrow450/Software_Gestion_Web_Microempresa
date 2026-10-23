<?php

namespace Tests\Feature;

use App\Models\Envasado;
use App\Models\Inventario;
use App\Models\Permiso;
use App\Models\VarianteProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class ListaPreparacionEnvasadoTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    private function datosIntegrados(array $e): array
    {
        return [
            'desde_lista' => 1, 'fecha_desde' => today()->toDateString(), 'fecha_hasta' => today()->addDay()->toDateString(),
            'produccion_desde' => today()->toDateString(), 'produccion_hasta' => today()->toDateString(),
            'presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id,
            'version_configuracion' => $e['configuracion']->version, 'cantidad' => 5,
        ];
    }

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

    public function test_modal_incluye_presentacion_y_configuracion_sin_abandonar_lista(): void
    {
        $e = $this->escenarioEnvasado();
        $this->pedidoEnvasado($e);
        $this->actingAs($e['usuario'])->get(route('cumplimiento.lista-preparacion'))->assertOk()
            ->assertSee('id="envasado-integrado"', false)
            ->assertSee('data-registrar-envasado="'.$e['presentacion']->id.'"', false)
            ->assertSee($e['configuracion']->nombre)->assertSee('Producción general (sin pedido)');
        $this->assertDatabaseCount('envasados', 0);
    }

    public function test_preview_integrada_no_escribe_y_confirmacion_idempotente_regresa_con_filtros(): void
    {
        $e = $this->escenarioEnvasado(100, 2);
        $pedido = $this->pedidoEnvasado($e);
        $datos = $this->datosIntegrados($e);
        $vista = $this->actingAs($e['usuario'])->post(route('envasado.previsualizar'), $datos, ['Accept' => 'application/json']);
        $vista->assertOk()->assertSee('data-confirmacion-envasado', false)->assertDontSee('<html', false)
            ->assertSee('5.000 unidad')->assertSee('name="desde_lista" value="1"', false);
        $this->assertDatabaseCount('envasados', 0);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(2, (int) Inventario::firstOrFail()->cantidad_actual);
        preg_match('/name="_token_operacion" value="([^"]+)"/', $vista->getContent(), $coincidencia);
        $datos['_token_operacion'] = html_entity_decode($coincidencia[1]);
        $filtros = array_intersect_key($datos, array_flip(['fecha_desde', 'fecha_hasta', 'produccion_desde', 'produccion_hasta']));
        $this->post(route('envasado.store'), $datos)->assertRedirect(route('cumplimiento.lista-preparacion', $filtros));
        $this->post(route('envasado.store'), $datos)->assertRedirect(route('cumplimiento.lista-preparacion', $filtros));
        $this->assertDatabaseCount('envasados', 1);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 7]);
        $this->assertSame('95.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertNull(Envasado::firstOrFail()->pedido_id);
        $this->assertSame('confirmado', $pedido->fresh()->estado);
        $this->pedidoEnvasado($e);
        $fila = $this->fila();
        $this->assertSame(5, $fila['envasadas']);
        $this->assertSame(3, $fila['faltante']);
        $this->assertDatabaseCount('envasados', 1);
    }

    public function test_preview_y_confirmacion_rechazan_usuario_sin_permiso_de_registro(): void
    {
        $e = $this->escenarioEnvasado();
        $this->pedidoEnvasado($e);
        $e['usuario']->role->permisos()->detach(Permiso::where('nombre', 'envasado.registrar')->firstOrFail()->id);
        $this->actingAs($e['usuario'])->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertDontSee('id="envasado-integrado"', false);
        $this->post(route('envasado.previsualizar'), $this->datosIntegrados($e))->assertForbidden();
        $this->post(route('envasado.store'), $this->datosIntegrados($e))->assertForbidden();
        $this->assertDatabaseCount('envasados', 0);
    }

    public function test_configuracion_obsoleta_y_stock_agotado_tras_preview_no_escriben(): void
    {
        $e = $this->escenarioEnvasado();
        $datos = $this->datosIntegrados($e);
        $this->actingAs($e['usuario'])->post(route('envasado.previsualizar'), $datos)->assertOk();
        $e['configuracion']->increment('version');
        $this->post(route('envasado.store'), $datos)->assertSessionHasErrors('configuracion_envasado_id');
        $datos['version_configuracion'] = $e['configuracion']->fresh()->version;
        $this->post(route('envasado.previsualizar'), $datos)->assertOk();
        $e['insumo']->update(['cantidad_actual' => 0]);
        $this->post(route('envasado.store'), $datos)->assertSessionHasErrors('materiales');
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_error_de_confirmacion_conserva_campos_para_reabrir_modal(): void
    {
        $e = $this->escenarioEnvasado(1);
        $this->pedidoEnvasado($e);
        $datos = $this->datosIntegrados($e);
        $this->actingAs($e['usuario'])->from(route('cumplimiento.lista-preparacion'))->post(route('envasado.store'), $datos)
            ->assertRedirect(route('cumplimiento.lista-preparacion'))->assertSessionHasErrors('materiales');
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertSee('data-reabrir="1"', false)->assertSee('value="5"', false);
        $this->assertDatabaseCount('envasados', 0);
    }

    public function test_variante_inactiva_permanece_en_demanda_pero_no_admite_registro(): void
    {
        $e = $this->escenarioEnvasado();
        $variante = VarianteProducto::create(['producto_id' => $e['presentacion']->producto_id, 'tipo' => 'Aroma', 'valor' => 'Lavanda', 'estado' => false]);
        $e['presentacion']->update(['variante_producto_id' => $variante->id]);
        $this->pedidoEnvasado($e);
        $this->actingAs($e['usuario'])->get(route('cumplimiento.lista-preparacion'))->assertOk()
            ->assertSee('Presentación inactiva')->assertDontSee('data-registrar-envasado="'.$e['presentacion']->id.'"', false);
        $this->postJson(route('envasado.previsualizar'), $this->datosIntegrados($e))->assertUnprocessable()->assertJsonValidationErrors('presentacion_producto_id');
    }

    public function test_filtros_de_retorno_invalidos_se_rechazan_antes_de_registrar(): void
    {
        $e = $this->escenarioEnvasado();
        $datos = $this->datosIntegrados($e);
        $datos['fecha_desde'] = 'https://otro.example';
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $datos)->assertSessionHasErrors('fecha_desde');
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_registro_conserva_consulta_sin_limites_y_muestra_atrasados_en_preparacion(): void
    {
        $e = $this->escenarioEnvasado();
        $atrasado = $this->pedidoEnvasado($e, 'en_preparacion');
        $atrasado->update(['fecha_entrega' => today()->subDays(10)]);
        $futuro = $this->pedidoEnvasado($e);
        $futuro->update(['fecha_entrega' => today()->addDays(30)]);
        $this->actingAs($e['usuario']);
        $this->assertSame(5, $this->fila()['total_unidades']);
        $datos = $this->datosIntegrados($e);
        $datos['fecha_desde'] = '';
        $datos['fecha_hasta'] = '';
        $destino = route('cumplimiento.lista-preparacion', array_intersect_key($datos, array_flip(['fecha_desde', 'fecha_hasta', 'produccion_desde', 'produccion_hasta'])));
        $this->post(route('envasado.store'), $datos)->assertRedirect($destino);
        $respuesta = $this->get($destino)->assertOk();
        $this->assertSame(10, $respuesta->viewData('filas')->first()['total_unidades']);
        $this->assertSame('en_preparacion', $atrasado->fresh()->estado);
    }
}
