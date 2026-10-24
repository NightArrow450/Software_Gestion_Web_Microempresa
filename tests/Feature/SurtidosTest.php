<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Services\ConfigurarFormatoSurtido;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarSurtidos;
use App\Services\TokenOperacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\SurtidosEscenario;
use Tests\TestCase;

class SurtidosTest extends TestCase
{
    use RefreshDatabase, SurtidosEscenario;

    public function test_habilitar_surtido_no_crea_productos_recetas_ni_inventario(): void
    {
        $e = $this->escenarioSurtido();
        $this->assertDatabaseCount('productos', 1);
        $this->assertDatabaseCount('presentaciones_producto', 3);
        $this->assertDatabaseCount('formato_surtido_skus', 3);
        $this->assertDatabaseCount('pedido_paquetes_surtidos', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame('24.00', number_format((float) DB::table('formatos_surtidos')->value('precio_empaque'), 2, '.', ''));
    }

    public function test_configuracion_rechaza_capacidades_incompatibles_y_no_sobrescribe_una_version_obsoleta(): void
    {
        $e = $this->escenarioSurtido();
        $e['skus'][1]->update(['contenido' => 2]);
        $this->expectException(ValidationException::class);
        app(ConfigurarFormatoSurtido::class)->guardar(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'No permitido', 'unidades_por_empaque' => 12, 'precio_empaque' => 24, 'estado' => true, 'version' => 0, 'skus' => [$e['skus'][0]->id, $e['skus'][1]->id]], $e['usuario'], $e['formato']);
    }

    private function registrar(array $e, ?array $paquetes = null, array $otros = []): Pedido
    {
        $pedidoBase = $this->pedidoEnvasado($e, 'registrado');
        $cliente = $pedidoBase->cliente_id;
        $pedidoBase->detalles()->delete();
        $pedidoBase->delete();
        $this->post(route('pedidos.store'), ['cliente_id' => $cliente, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado', 'detalles' => array_merge([['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'], 'modalidad' => 'empaque', 'cantidad' => 2, 'unidades_por_empaque' => 12, 'precio_unitario' => '0.01', 'paquetes_json' => json_encode($paquetes ?? $this->paquetesSurtidos($e))]], $otros)])->assertRedirect()->assertSessionHasNoErrors();

        return Pedido::latest('id')->firstOrFail();
    }

    public function test_registro_guarda_dos_composiciones_distintas_y_cobra_solo_dos_paquetes(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e);
        $d = $pedido->detalles->sole();
        $paquetes = app(GestionarSurtidos::class)->paquetes($d);
        $this->assertSame('48.00', $pedido->total);
        $this->assertSame('24.00', $d->precio_unitario);
        $this->assertNull($d->presentacion_producto_id);
        $this->assertCount(2, $paquetes);
        $this->assertSame([5, 4, 3], array_column($paquetes[0]['componentes'], 'cantidad'));
        $this->assertSame([2, 7, 3], array_column($paquetes[1]['componentes'], 'cantidad'));
        $this->assertSame(24, app(GestionarSurtidos::class)->lineasFisicas($pedido)->sum('cantidad'));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_pendiente_no_inventa_demanda_y_la_version_anterior_se_conserva(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e, []);
        $d = $pedido->detalles->sole();
        $servicio = app(GestionarSurtidos::class);
        $this->assertSame(24, $servicio->pendientes($pedido)[0]['unidades']);
        $this->assertCount(0, $servicio->lineasFisicas($pedido));
        $servicio->guardar($d, $this->paquetesSurtidos($e), $e['usuario'], 'Composición definida por operaciones comerciales.');
        $this->assertSame([], $servicio->pendientes($pedido));
        $this->assertDatabaseCount('paquete_surtido_composiciones', 4);
        $this->assertSame(4, DB::table('auditoria')->where('accion', 'composicion_surtido_modificada')->count());
    }

    public function test_composicion_incompleta_rechaza_y_no_deja_versiones_parciales(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e, []);
        $paquetes = $this->paquetesSurtidos($e);
        $paquetes[1]['componentes'][2]['cantidad'] = 2;
        try {
            app(GestionarSurtidos::class)->guardar($pedido->detalles->sole(), $paquetes, $e['usuario'], 'Intento de composición que no completa el paquete.');
            $this->fail('Debe rechazar once unidades.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('paquetes', $exception->errors());
        }
        $this->assertDatabaseCount('paquete_surtido_composiciones', 2);
        $this->assertDatabaseCount('paquete_surtido_componentes', 0);
    }

    public function test_formulario_catalogo_y_detalle_muestran_composicion_sin_duplicar_importes(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e);
        $this->get(route('pedidos.create'))->assertOk()->assertSee('Añadir paquete surtido habilitado')->assertSee('surtidos.js');
        $this->get(route('surtidos.index'))->assertOk()->assertSee('composición de aromas');
        $this->get(route('pedidos.show', $pedido))->assertOk()->assertSee('Paquete 1')->assertSee('Paquete 2')->assertSee('Lavanda');
    }

    public function test_cambio_con_version_obsoleta_y_sku_ajeno_no_modifican_la_composicion(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e);
        $d = $pedido->detalles->sole();
        $this->post(route('surtidos.componer', [$pedido, $d]), ['version' => 99, 'motivo' => 'Cambio obsoleto de composición.', 'paquetes_json' => json_encode($this->paquetesSurtidos($e))])->assertSessionHasErrors('version');
        $this->assertDatabaseCount('paquete_surtido_composiciones', 2);
        $datos = $this->paquetesSurtidos($e);
        $datos[0]['componentes'][0]['presentacion_producto_id'] = 999999;
        $this->post(route('surtidos.componer', [$pedido, $d]), ['version' => $pedido->version, 'motivo' => 'Intento con un SKU no autorizado.', 'paquetes_json' => json_encode($datos)])->assertSessionHasErrors('paquetes');
        $this->assertDatabaseCount('paquete_surtido_composiciones', 2);
    }

    public function test_finalizar_y_despachar_bloquean_composiciones_pendientes(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e, []);
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'en_preparacion']);
        $this->post(route('cumplimiento.finalizar-preparacion', $pedido))->assertSessionHasErrors('composicion');
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasErrors('composicion');
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_despacho_componentes', 0);
    }

    public function test_despacho_agrega_sku_normal_y_surtido_sin_descuentos_duplicados(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e, null, [['presentacion_producto_id' => $e['skus'][0]->id, 'modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'cantidad' => 2]]);
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][0]->id, 'cantidad_actual' => 69]);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][1]->id, 'cantidad_actual' => 89]);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][2]->id, 'cantidad_actual' => 94]);
        $this->assertDatabaseCount('movimientos_inventario', 3);
        $this->assertDatabaseCount('pedido_despacho_componentes', 6);
        $this->assertSame(4800, (int) DB::table('pedido_despacho_componentes')->sum('importe_centimos'));
        $r = app(ControlEntregaPedido::class)->resumen($pedido->fresh());
        $this->assertCount(7, $r['lineas']);
        $this->assertNotContains(false, array_column($r['lineas'], 'verificada'));
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('movimientos_inventario', 3);
    }

    public function test_stock_insuficiente_revierte_todos_los_sku_y_la_fotografia(): void
    {
        $e = $this->escenarioSurtido(5);
        $pedido = $this->registrar($e);
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasErrors('inventario');
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_despacho_componentes', 0);
        $this->assertSame(15, (int) DB::table('inventarios')->sum('cantidad_actual'));
        $this->assertSame('listo_despacho', $pedido->fresh()->estado);
    }

    public function test_rechazo_y_retorno_identifican_el_aroma_y_reincorporan_solo_una_vez(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e);
        $d = $pedido->detalles->sole();
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $fisica = DB::table('pedido_despacho_componentes')->where('presentacion_producto_id', $e['skus'][0]->id)->orderBy('id')->first();
        $this->post(route('cumplimiento.resultado-entrega', $pedido), ['version' => 0, 'detalles' => [['pedido_detalle_id' => $d->id, 'despacho_componente_id' => $fisica->id, 'aceptadas' => 3, 'rechazadas' => 2, 'motivo' => 'El cliente rechaza estas dos unidades del aroma indicado.']]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][0]->id, 'cantidad_actual' => 93]);
        $this->post(route('cumplimiento.retornos', $pedido), ['version' => 1, 'observaciones' => 'Conteo físico con una unidad apta y una dañada.', 'detalles' => [['pedido_detalle_id' => $d->id, 'despacho_componente_id' => $fisica->id, 'aptas' => 1, 'danadas' => 1, 'en_revision' => 0, 'no_recuperadas' => 0]]])->assertSessionHasNoErrors();
        $retorno = DB::table('retorno_pedido_detalles')->first();
        $datos = ['version' => 2, 'retorno_detalle_id' => $retorno->id, 'cantidad' => 1, 'motivo' => 'Se confirma que la unidad de Lavanda está apta.', '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->post(route('cumplimiento.aprobar-retorno', $pedido), $datos)->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.aprobar-retorno', $pedido), $datos)->assertRedirect();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][0]->id, 'cantidad_actual' => 94]);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][1]->id, 'cantidad_actual' => 89]);
        $this->assertSame(1, DB::table('movimientos_inventario')->where('origen_tipo', 'retorno_pedido')->count());
        $this->assertSame('48.00', $pedido->fresh()->total);
        $this->get(route('cumplimiento.show', $pedido))->assertOk()->assertSee('Lavanda');
    }
}
