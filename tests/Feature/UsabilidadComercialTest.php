<?php

namespace Tests\Feature;

use App\Models\Inventario;
use App\Models\Permiso;
use App\Services\AtenderPedidoUnidades;
use App\Services\GestionarPedidoUnidades;
use App\Services\TokenOperacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsabilidadEscenario;

class UsabilidadComercialTest extends TestCase
{
    use RefreshDatabase, UsabilidadEscenario;

    public function test_busqueda_parcial_sin_tildes_paginada_y_solo_sku_vendibles_en_sqlite(): void
    {
        $this->comprobarBusquedaComercial();
    }

    public function test_retiro_54_a_32_despachadas_y_aceptadas_con_historia_e_idempotencia(): void
    {
        $this->comprobarRetiroTrasEntregaParcial();
    }

    public function test_retiro_no_admite_cantidades_superiores_version_obsoleta_sin_motivo_o_sin_permiso(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->pedidoUnico($e, 10);
        $detalle = $pedido->detalles()->sole();
        $datos = ['version' => $pedido->version, 'retirar' => [$detalle->id => 2], 'motivo' => 'El cliente ya no desea las unidades pendientes.'];
        foreach ([['retirar' => [$detalle->id => 11]], ['version' => 999], ['motivo' => ''], ['retirar' => [999999 => 1]], ['retirar' => [$detalle->id => '1.5']], ['retirar' => [$detalle->id => 0]]] as $invalido) {
            $this->postJson(route('pedidos.retirar-faltantes', $pedido), array_replace($datos, $invalido, ['_token_operacion' => TokenOperacion::emitir($e['usuario']->id)]))->assertUnprocessable();
        }
        $e['usuario']->role->permisos()->detach(Permiso::where('nombre', 'pedidos.editar')->value('id'));
        $e['usuario']->unsetRelation('role');
        $this->postJson(route('pedidos.retirar-faltantes', $pedido), $datos + ['_token_operacion' => TokenOperacion::emitir($e['usuario']->id)])->assertForbidden();
        $this->assertSame(10, (int) $detalle->fresh()->cantidad);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_versiones', 1);
    }

    public function test_resuelve_paquetes_pendientes_sin_modificar_el_ya_despachado(): void
    {
        $e = $this->escenarioUnidades(24);
        $grupo = ['capacidad' => 12, 'componentes' => [['sku_id' => $e['presentacion']->id, 'cantidad' => 12]]];
        $pedido = $this->pedidoUnico($e, 24, [$grupo, $grupo]);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        $detalle = $pedido->detalles()->sole();
        $servicio = app(AtenderPedidoUnidades::class);
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 12, 'motivo' => 'Preparación del primer paquete físicamente disponible.'], $e['usuario']);
        $pedido->refresh();
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id];
        $identidad = $foto['agrupaciones'][0]['identidad'];
        $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => (string) Str::ulid(), 'motivo' => 'Sale solamente el primer paquete de doce unidades.', 'paquetes' => [$identidad], 'detalles' => [['id' => $detalle->id, 'cantidad' => 12]]], $e['usuario']);
        $pedido->refresh();
        $salidas = DB::table('pedido_despachos')->get()->toArray();
        $datos = ['version' => $pedido->version, 'retirar' => [$detalle->id => 12], 'motivo' => 'El cliente ya no desea el paquete que todavía no salió.'];
        $this->postJson(route('pedidos.retirar-faltantes', $pedido), $datos + ['_token_operacion' => TokenOperacion::emitir($e['usuario']->id)])->assertUnprocessable()->assertJsonValidationErrors('agrupaciones');
        $this->assertSame(24, (int) $detalle->fresh()->cantidad);
        $this->post(route('pedidos.retirar-faltantes', $pedido), $datos + ['resolver_agrupaciones' => 1, '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)])->assertSessionHasNoErrors();
        $actual = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id];
        $this->assertCount(1, $actual['agrupaciones']);
        $this->assertSame($identidad, $actual['agrupaciones'][0]['identidad']);
        $this->assertEquals($salidas, DB::table('pedido_despachos')->get()->toArray());
        $this->assertSame(12, (int) $detalle->fresh()->cantidad);
    }

    public function test_disponibilidad_separa_demanda_espera_y_stock_sin_materiales_operativos(): void
    {
        $e = $this->escenarioUnidades(32);
        config(['envasado.control_materiales_exacto' => false]);
        $pedido = $this->pedidoUnico($e, 54);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->detalles()->sole()->update(['cantidad_espera' => 10]);
        $respuesta = $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertOk()->assertSee('Unidades terminadas registradas')->assertDontSee($e['insumo']->codigo);
        $fila = $respuesta->viewData('revision')->sole();
        $this->assertSame(54, $fila['necesario']);
        $this->assertSame(10, $fila['en_espera']);
        $this->assertSame(44, $fila['activo']);
        $this->assertSame(32, $fila['disponible']);
        $this->assertSame(12, $fila['faltante_activo']);
        $this->assertSame(22, $fila['faltante_total']);
        $this->get(route('inventario.index', ['historico_materiales' => 1]))->assertOk()->assertSee('Existencias físicas no verificadas')->assertSee($e['insumo']->codigo);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        $pedido->update(['estado' => 'entregado']);
        $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertNotFound();
    }

    public function test_historial_de_incidencias_conserva_registros_y_no_genera_inventario(): void
    {
        $e = $this->escenarioUnidades(32);
        $pedido = $this->pedidoUnico($e, 54);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        $detalle = $pedido->detalles()->sole();
        foreach (['envases', 'etiquetas_tapas'] as $incidencia) {
            app(AtenderPedidoUnidades::class)->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 32, 'incidencia' => $incidencia, 'motivo' => 'No fue posible completar las otras unidades por falta de material.'], $e['usuario']);
            $pedido->refresh();
        }
        $this->get(route('cumplimiento.show', $pedido))->assertOk()->assertSee('Historial de preparación e incidencias')->assertSee('Faltan envases')->assertSee('Faltan etiquetas o tapas');
        $this->get(route('seguimiento.show', $pedido))->assertOk()->assertSee('Faltan envases');
        $this->assertDatabaseCount('pedido_preparaciones', 2);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame(32, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
    }
}
