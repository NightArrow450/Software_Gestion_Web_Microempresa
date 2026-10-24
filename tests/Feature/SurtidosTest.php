<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Permiso;
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

    public function test_configuracion_rechaza_capacidades_incompatibles(): void
    {
        $e = $this->escenarioSurtido();
        $e['skus'][1]->update(['contenido' => 2]);
        $this->expectException(ValidationException::class);
        app(ConfigurarFormatoSurtido::class)->guardar(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'No permitido', 'unidades_por_empaque' => 12, 'precio_empaque' => 24, 'estado' => true, 'version' => 0, 'skus' => [$e['skus'][0]->id, $e['skus'][1]->id]], $e['usuario'], $e['formato']);
    }

    public function test_configuracion_actualiza_sin_recetas_y_rechaza_version_obsoleta(): void
    {
        $e = $this->escenarioSurtido();
        $datos = ['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'Surtido revisado', 'unidades_por_empaque' => 15, 'precio_empaque' => 30, 'estado' => true, 'version' => 0, 'skus' => array_column($e['skus'], 'id')];
        app(ConfigurarFormatoSurtido::class)->guardar($datos, $e['usuario'], $e['formato']);
        $this->assertDatabaseHas('formatos_surtidos', ['id' => $e['formato'], 'version' => 1, 'unidades_por_empaque' => 15]);
        try {
            app(ConfigurarFormatoSurtido::class)->guardar(array_replace($datos, ['precio_empaque' => 1]), $e['usuario'], $e['formato']);
            $this->fail('No debe sobrescribir una versión más reciente.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('version', $exception->errors());
        }
        $this->assertSame(30.0, (float) DB::table('formatos_surtidos')->value('precio_empaque'));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_sin_permiso_no_se_puede_configurar_ni_componer_aunque_se_pueda_leer_el_pedido(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e);
        $e['usuario']->role->permisos()->detach(Permiso::whereIn('nombre', ['surtidos.configurar', 'pedidos.surtidos.componer'])->pluck('id'));
        $e['usuario']->unsetRelation('role');
        $this->get(route('surtidos.index'))->assertForbidden();
        $this->post(route('surtidos.componer', [$pedido, $pedido->detalles->sole()]), ['version' => 0, 'motivo' => 'No autorizado para modificar paquetes.', 'paquetes_json' => json_encode($this->paquetesSurtidos($e))])->assertForbidden();
        $this->get(route('pedidos.show', $pedido))->assertOk()->assertSee('Lavanda');
        $this->assertDatabaseCount('paquete_surtido_composiciones', 2);
    }

    public function test_no_permite_cambiar_composiciones_despues_del_cierre(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e);
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'listo_despacho']);
        $datos = $this->paquetesSurtidos($e);
        $datos[0] = $datos[1];
        $this->post(route('surtidos.componer', [$pedido, $pedido->detalles->sole()]), ['version' => 0, 'motivo' => 'Intento posterior al cierre de preparación.', 'paquetes_json' => json_encode($datos)])->assertSessionHasErrors();
        $this->assertDatabaseCount('paquete_surtido_composiciones', 2);
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

    public function test_lista_consolida_aromas_y_senala_pendientes_sin_ocultar_filtros(): void
    {
        $e = $this->escenarioSurtido();
        $paquetes = $this->paquetesSurtidos($e);
        $paquetes[1] = ['estado' => 'pendiente', 'componentes' => []];
        $pedido = $this->registrar($e, $paquetes);
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'confirmado']);
        $r = $this->get(route('cumplimiento.lista-preparacion'));
        $r->assertOk()->assertSee('12 unidades pendientes de distribuir')->assertSee('Pendiente de composición surtida');
        $this->assertSame(12, $r->viewData('filas')->sum('total_unidades'));
        $this->assertCount(3, $r->viewData('filas'));
        $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertOk()->assertViewHas('puedeAtender', false);
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'despachado']);
        $this->get(route('cumplimiento.lista-preparacion'))->assertViewHas('filas', fn ($filas): bool => $filas->isEmpty());
    }

    public function test_impresion_incluye_anexo_y_cambio_catalogo_no_reescribe_composicion_ni_precio(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = $this->registrar($e);
        DB::table('formatos_surtidos')->where('id', $e['formato'])->update(['precio_empaque' => 99, 'unidades_por_empaque' => 15]);
        $e['skus'][0]->variante->update(['valor' => 'Nombre nuevo']);
        $this->get(route('pedidos.show', $pedido))->assertOk()->assertSee('Lavanda')->assertSee('Historial de composiciones');
        $this->get(route('pedidos.edit', $pedido))->assertOk()->assertSee('24.00');
        $this->assertSame('48.00', $pedido->fresh()->total);
        $respuesta = $this->get(route('pedidos.imprimir-por-fecha', ['fecha_pedido' => today()->toDateString()]));
        $respuesta->assertOk()->assertSee('Anexo de composición')->assertSee('Paquete 2')->assertSee('Lavanda');
    }
}
