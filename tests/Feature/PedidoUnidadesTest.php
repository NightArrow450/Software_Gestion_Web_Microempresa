<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Services\AprobarRetornoPedido;
use App\Services\AtenderPedidoUnidades;
use App\Services\ControlEntregaPedido;
use App\Services\FilasIndicadores;
use App\Services\FiltrosIndicadores;
use App\Services\GestionarPedidoUnidades;
use App\Services\RegistrarResultadoEntrega;
use App\Services\RegistrarRetornoPedido;
use App\Services\TokenOperacion;
use App\Services\TransicionesPedido;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\SurtidosEscenario;
use Tests\TestCase;

class PedidoUnidadesTest extends TestCase
{
    use RefreshDatabase, SurtidosEscenario;

    private function entrada(array $e, int $cantidad = 54): array
    {
        $this->freezeTime();
        $cliente = Cliente::create(['nombre' => 'Cliente por unidades', 'estado' => true]);

        return ['cliente_id' => $cliente->id, 'fecha_pedido' => today('America/Lima')->toDateString(), 'fecha_entrega' => today('America/Lima')->toDateString(),
            'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => $cantidad]]];
    }

    public static function cantidades(): array
    {
        return ['una unidad' => [1], 'cinco sin paquete' => [5], 'siete sin múltiplo' => [7], 'cincuenta y cuatro' => [54], 'sin máximo comercial de diez mil' => [10001]];
    }

    #[DataProvider('cantidades')]
    public function test_vende_unidades_sin_multiplo_y_sin_stock(int $cantidad): void
    {
        $e = $this->escenarioSurtido(0);
        $entrada = $this->entrada($e, $cantidad);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);
        app(TransicionesPedido::class)->registrar($pedido, 'registrado', 'confirmado', $e['usuario']->id, [], 'Confirmación sin disponibilidad.');

        $this->assertDatabaseHas('pedido_detalles', ['pedido_id' => $pedido->id, 'cantidad' => $cantidad, 'modalidad' => 'unidad', 'unidades_por_empaque' => null]);
        $this->assertSame('unidades', $pedido->modelo_comercial);
        $this->assertDatabaseCount('pedido_agrupaciones', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => 'confirmado']);
    }

    public function test_agrupa_variantes_sin_duplicar_precio_y_conserva_sueltas(): void
    {
        $e = $this->escenarioSurtido();
        $entrada = $this->entrada($e, 5);
        $entrada['detalles'] = array_map(fn ($sku, $n): array => ['presentacion_producto_id' => $sku->id, 'cantidad' => $n], $e['skus'], [5, 24, 24]);
        $entrada['agrupaciones'] = [['capacidad' => 24, 'componentes' => [['sku_id' => $e['skus'][1]->id, 'cantidad' => 24]]],
            ['capacidad' => 30, 'componentes' => [['sku_id' => $e['skus'][2]->id, 'cantidad' => 24]]]];
        $pedido = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);

        $this->assertSame('106.00', $pedido->total);
        $this->assertSame(53, (int) $pedido->detalles()->sum('cantidad'));
        $this->assertDatabaseCount('pedido_agrupaciones', 2);
        $this->assertSame(48, (int) DB::table('pedido_agrupacion_detalles')->sum('cantidad'));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_reduccion_conserva_version_original_y_no_modifica_produccion(): void
    {
        $e = $this->escenarioSurtido(32);
        $entrada = $this->entrada($e);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);
        $pedido->update(['estado' => 'en_preparacion']);
        $pedido->refresh();
        $detalle = $pedido->detalles()->sole();
        $entrada['detalles'][0] += ['id' => $detalle->id];
        $entrada['detalles'][0]['cantidad'] = 32;
        $entrada['_version'] = $pedido->version;
        $entrada['motivo'] = 'Se agotó el insumo químico; se retiran definitivamente 22 unidades.';
        $nuevo = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario'], $pedido);
        $foto = json_decode(DB::table('pedido_versiones')->orderBy('id')->first()->datos, true);

        $this->assertSame(54, $foto['lineas'][0]['cantidad']);
        $this->assertSame(32, (int) $nuevo->detalles()->sole()->cantidad);
        $this->assertSame('64.00', $nuevo->total);
        $this->assertSame(32, (int) Inventario::first()->cantidad_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_versiones', 2);
        $entrada['_version'] = $nuevo->version;
        $entrada['motivo'] = 'Se aclara la dirección sin borrar la reducción comercial anterior.';
        $entrada['observaciones'] = 'Consultar el ajuste anterior al entregar.';
        $nuevo = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario'], $nuevo);
        $this->get(route('cumplimiento.show', $nuevo))->assertOk()->assertViewHas('cambiosOriginales', function (array $cambios): bool {
            return count($cambios) === 1 && $cambios[0]['original'] === 54 && $cambios[0]['vigente'] === 32;
        })->assertSee('Reparto debe explicar las sustituciones');
    }

    public function test_despachos_parciales_no_duplican_stock_ni_marcan_entrega_total(): void
    {
        $e = $this->escenarioSurtido(54);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($this->entrada($e), $e['usuario']);
        $pedido->update(['estado' => 'en_preparacion']);
        $pedido->refresh();
        $detalle = $pedido->detalles()->sole();
        $servicio = app(AtenderPedidoUnidades::class);
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 32, 'motivo' => 'Preparación física parcial verificada.'], $e['usuario']);
        $pedido->refresh();
        $datos = ['version_pedido' => $pedido->version, 'operacion' => 'primera_salida_00001', 'motivo' => 'Se despachan 32 y quedan 22 por atender.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 32]]];
        $id = $servicio->despachar($pedido, $datos, $e['usuario']);
        $this->assertSame($id, $servicio->despachar($pedido, $datos, $e['usuario']));
        $pedido->refresh();
        $resumen = app(ControlEntregaPedido::class)->resumen($pedido);
        $fisica = array_values($resumen['lineas'])[0];
        app(RegistrarResultadoEntrega::class)->ejecutar($pedido, ['version' => $resumen['version'], 'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aceptadas' => 32, 'rechazadas' => 0]]], $e['usuario']);

        $this->assertSame(22, (int) Inventario::first()->cantidad_actual);
        $this->assertSame('despacho_parcial', $pedido->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $pedido->refresh();
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 22, 'motivo' => 'Se preparó físicamente el remanente.'], $e['usuario']);
        $pedido->refresh();
        $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => 'segunda_salida_00002', 'motivo' => 'Se despachan las 22 unidades restantes.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 22]]], $e['usuario']);
        $this->assertSame(0, (int) Inventario::first()->cantidad_actual);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertSame(54, (int) $servicio->despachadas($pedido->id)->get($detalle->id));
        $this->assertCount(2, $servicio->fotografia($pedido));
    }

    public function test_rechaza_asignar_mas_unidades_de_las_vendidas_y_revierte_el_pedido_completo(): void
    {
        $e = $this->escenarioSurtido();
        $entrada = $this->entrada($e, 5);
        $entrada['agrupaciones'] = [['capacidad' => 24, 'componentes' => [['sku_id' => $e['presentacion']->id, 'cantidad' => 6]]]];
        try {
            app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);
            $this->fail('Debía rechazarse una distribución que inventa unidades.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('supera las unidades vendidas', $exception->getMessage());
        }
        $this->assertDatabaseCount('pedidos', 0);
        $this->assertDatabaseCount('pedido_detalles', 0);
    }

    public function test_formulario_nuevo_y_guardado_http_utilizan_unidades(): void
    {
        $e = $this->escenarioSurtido();
        $this->get(route('pedidos.create'))->assertOk()->assertSee('Pedidos por unidades físicas')->assertDontSee('Añadir paquete surtido habilitado');
        $datos = $this->entrada($e, 7);
        $this->post(route('pedidos.store'), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::sole();
        $this->assertSame('14.00', $pedido->total);
        $this->assertSame('unidades', $pedido->modelo_comercial);
        $respuesta = $this->get(route('pedidos.show', $pedido));
        $this->assertNull($respuesta->exception, $respuesta->exception?->getMessage() ?? '');
        $respuesta->assertOk()->assertSee('Historial de modificaciones comerciales');
        $this->get(route('cumplimiento.show', $pedido))->assertOk()->assertSee('Preparación y atención por unidades');
    }

    public function test_servidor_no_permite_crear_un_nuevo_pedido_como_empaque(): void
    {
        $e = $this->escenarioSurtido();
        $datos = $this->entrada($e, 5);
        $datos['detalles'][0]['modalidad'] = 'empaque';
        $datos['detalles'][0]['unidades_por_empaque'] = 12;
        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors('detalles.0.modalidad');
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_servidor_rechaza_precio_faltante_sin_inventarlo(): void
    {
        $e = $this->escenarioSurtido();
        $e['presentacion']->update(['precio_unitario' => null]);
        $this->post(route('pedidos.store'), $this->entrada($e, 5))->assertSessionHasErrors('detalles.0.presentacion_producto_id');
        $this->assertDatabaseCount('pedidos', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_agrupaciones_rechazan_capacidades_fisicas_incompatibles(): void
    {
        $e = $this->escenarioSurtido();
        $e['skus'][1]->update(['contenido' => 2]);
        $datos = $this->entrada($e, 5);
        $datos['detalles'][] = ['presentacion_producto_id' => $e['skus'][1]->id, 'cantidad' => 7];
        $datos['agrupaciones_json'] = json_encode([['capacidad' => 12, 'componentes' => [['sku_id' => $e['skus'][0]->id, 'cantidad' => 5], ['sku_id' => $e['skus'][1]->id, 'cantidad' => 7]]]]);
        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors('agrupaciones');
        $this->assertDatabaseCount('pedidos', 0);
        $this->assertDatabaseCount('pedido_versiones', 0);
    }

    public function test_edicion_conserva_precio_pactado_y_version_original_aunque_cambie_catalogo(): void
    {
        $e = $this->escenarioSurtido();
        $datos = $this->entrada($e, 5);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $e['presentacion']->update(['precio_unitario' => 7]);
        $datos['detalles'][0] += ['id' => $pedido->detalles()->sole()->id];
        $datos['detalles'][0]['cantidad'] = 3;
        $datos['_version'] = $pedido->version;
        $datos['motivo'] = 'Reducción comercial conservando el precio pactado.';
        $nuevo = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario'], $pedido);
        $this->assertSame('6.00', $nuevo->total);
        $this->assertSame('2.00', $nuevo->detalles()->sole()->precio_unitario);
        $this->assertDatabaseCount('pedido_versiones', 2);
    }

    public function test_dividir_y_reducir_aromas_conserva_original_y_aplica_precio_del_nuevo_sku(): void
    {
        $e = $this->escenarioSurtido();
        $datos = $this->entrada($e, 24);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $e['skus'][1]->update(['precio_unitario' => 3]);
        $datos['detalles'][0] += ['id' => $pedido->detalles()->sole()->id];
        $datos['detalles'][0]['cantidad'] = 8;
        $datos['detalles'][] = ['presentacion_producto_id' => $e['skus'][1]->id, 'cantidad' => 7];
        $datos['_version'] = $pedido->version;
        $datos['motivo'] = 'Solo se dispone físicamente de estos aromas y cantidades.';
        $nuevo = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario'], $pedido);
        $this->assertSame(15, (int) $nuevo->detalles()->sum('cantidad'));
        $this->assertSame('37.00', $nuevo->total);
        $original = json_decode(DB::table('pedido_versiones')->where('numero', 1)->value('datos'), true);
        $this->assertSame(24, $original['lineas'][0]['cantidad']);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_retirar_linea_no_elimina_su_registro_ni_su_historial(): void
    {
        $e = $this->escenarioSurtido();
        $datos = $this->entrada($e, 5);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $id = $pedido->detalles()->sole()->id;
        $datos['detalles'][0] += ['id' => $id];
        $datos['detalles'][0]['cantidad'] = 0;
        $datos['_version'] = $pedido->version;
        $datos['motivo'] = 'Se retira definitivamente esta obligación comercial.';
        $nuevo = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario'], $pedido);
        $this->assertSame('0.00', $nuevo->total);
        $this->assertFalse($nuevo->detalles()->exists());
        $this->assertDatabaseCount('pedido_detalles', 1);
        $this->assertNotNull(DB::table('pedido_detalles')->where('id', $id)->value('retirado_en'));
        $this->assertDatabaseCount('pedido_versiones', 2);
    }

    public function test_cierre_no_declara_completa_la_preparacion_de_32_si_siguen_solicitadas_54(): void
    {
        $e = $this->escenarioSurtido(32);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($this->entrada($e), $e['usuario']);
        $pedido->update(['estado' => 'en_preparacion']);
        $pedido->refresh();
        app(AtenderPedidoUnidades::class)->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $pedido->detalles()->sole()->id, 'cantidad' => 32, 'motivo' => 'Hay 32 unidades físicas; no se inventa el resto.'], $e['usuario']);
        $this->post(route('cumplimiento.finalizar-preparacion', $pedido))->assertSessionHasErrors('estado');
        $this->assertSame('en_preparacion', $pedido->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_catalogo_de_pedidos_consulta_paginas_sin_cargar_todos_los_sku_en_html(): void
    {
        $e = $this->escenarioSurtido();
        $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'skus', 'producto_id' => $e['presentacion']->producto_id]))
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.precio_unitario', '2.00');
        $this->get(route('pedidos.create'))->assertOk()->assertDontSee($e['presentacion']->codigo_sku);
    }

    public function test_sin_permiso_comercial_no_puede_guardar_aunque_manipule_el_formulario(): void
    {
        $e = $this->escenarioSurtido();
        $e['usuario']->role->permisos()->detach(Permiso::where('nombre', 'pedidos.crear')->value('id'));
        $e['usuario']->unsetRelation('role');
        $this->post(route('pedidos.store'), $this->entrada($e, 5))->assertForbidden();
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_indicadores_y_retornos_utilizan_solo_las_unidades_realmente_despachadas(): void
    {
        $e = $this->escenarioSurtido(100);
        $datos = $this->entrada($e, 5);
        $datos['detalles'][] = ['presentacion_producto_id' => $e['skus'][1]->id, 'cantidad' => 3];
        $datos['agrupaciones'] = [['capacidad' => 8, 'componentes' => [['sku_id' => $e['skus'][0]->id, 'cantidad' => 5], ['sku_id' => $e['skus'][1]->id, 'cantidad' => 3]]]];
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $pedido->update(['estado' => 'en_preparacion']);
        $pedido->refresh();
        $filtros = fn (string $reporte): array => FiltrosIndicadores::validar(['reporte' => $reporte, 'pedido_id' => $pedido->id, 'desde' => $datos['fecha_pedido'], 'hasta' => $datos['fecha_entrega'], 'economico' => 1]);
        $fuente = app(FilasIndicadores::class);
        $comerciales = iterator_to_array($fuente->filas($filtros('comercial')), false);
        $grupos = iterator_to_array($fuente->filas($filtros('composiciones')), false);
        $this->assertSame(1600, array_sum(array_column($comerciales, 'importe_centimos')));
        $this->assertSame(8, array_sum(array_column($grupos, 'unidades_fisicas')));
        $this->assertSame(['unidades'], array_values(array_unique(array_column($grupos, 'modelo_comercial'))));
        $datos['detalles'] = $pedido->detalles()->get()->map(fn ($linea): array => ['id' => $linea->id, 'presentacion_producto_id' => $linea->presentacion_producto_id, 'cantidad' => $linea->cantidad])->all();
        $datos['_version'] = $pedido->version;
        $datos['motivo'] = 'Se redistribuyen tres unidades sueltas para esta salida parcial.';
        $datos['agrupaciones'] = [['capacidad' => 5, 'componentes' => [['sku_id' => $e['skus'][0]->id, 'cantidad' => 2], ['sku_id' => $e['skus'][1]->id, 'cantidad' => 3]]]];
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario'], $pedido);
        $d = $pedido->detalles()->orderBy('id')->first();
        $atencion = app(AtenderPedidoUnidades::class);
        $atencion->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $d->id, 'cantidad' => 3, 'motivo' => 'Preparación parcial físicamente comprobada.'], $e['usuario']);
        $pedido->refresh();
        $atencion->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => 'salida_para_retorno_0001', 'motivo' => 'Se despachan solo tres unidades preparadas.', 'detalles' => [['id' => $d->id, 'cantidad' => 3]]], $e['usuario']);
        $pedido->refresh();
        $pendientes = iterator_to_array($fuente->filas($filtros('fisicas')), false);
        $this->assertSame(5, array_sum(array_column($pendientes, 'unidades_fisicas')));
        $control = app(ControlEntregaPedido::class);
        $resumen = $control->resumen($pedido);
        $fisica = array_values($resumen['lineas'])[0];
        app(RegistrarResultadoEntrega::class)->ejecutar($pedido, ['version' => $resumen['version'], 'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aceptadas' => 2, 'rechazadas' => 1, 'motivo' => 'El cliente no acepta este aroma sustituido.']]], $e['usuario']);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 97]);
        $resumen = $control->resumen($pedido->fresh());
        app(RegistrarRetornoPedido::class)->ejecutar($pedido, ['version' => $resumen['version'], 'observaciones' => 'Se recibió físicamente una unidad apta.', 'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aptas' => 1, 'danadas' => 0, 'en_revision' => 0, 'no_recuperadas' => 0]]], $e['usuario']);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 97]);
        $resumen = $control->resumen($pedido->fresh());
        app(AprobarRetornoPedido::class)->ejecutar($pedido, ['version' => $resumen['version'], 'retorno_detalle_id' => $resumen['retornos']->sole()->id, 'cantidad' => 1, 'motivo' => 'Reincorporación aprobada después de la revisión.'], $e['usuario']);
        $this->assertSame(98, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $situacion = iterator_to_array($fuente->filas($filtros('situacion_entregas')), false);
        $this->assertCount(1, $situacion);
        $this->assertSame(3, $situacion[0]['despachadas']);
        $this->assertSame(2, $situacion[0]['aceptadas']);
        $this->assertSame(1, $situacion[0]['rechazadas']);
        $this->assertSame(1, $situacion[0]['reincorporadas']);
        $movimientos = iterator_to_array($fuente->filas($filtros('movimientos')), false);
        $this->assertCount(1, $movimientos);
        $this->assertSame($pedido->id, $movimientos[0]['pedido_id']);
    }

    public function test_versiones_comerciales_no_se_pueden_sobrescribir_por_sql(): void
    {
        $e = $this->escenarioSurtido();
        $pedido = app(GestionarPedidoUnidades::class)->guardar($this->entrada($e), $e['usuario']);
        $this->expectException(QueryException::class);
        DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->update(['datos' => '{}']);
    }

    public function test_dos_parciales_identifican_paquetes_concretos_y_sueltas_sin_repetirlos(): void
    {
        $e = $this->escenarioSurtido(10);
        $datos = $this->entrada($e, 6);
        $datos['agrupaciones'] = array_fill(0, 2, ['capacidad' => 4, 'componentes' => [['sku_id' => $e['presentacion']->id, 'cantidad' => 2]]]);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id];
        $pedido->update(['estado' => 'en_preparacion']);
        $pedido->refresh();
        $detalle = $pedido->detalles()->sole();
        $servicio = app(AtenderPedidoUnidades::class);
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 6, 'motivo' => 'Se verificaron seis unidades físicamente preparadas.'], $e['usuario']);
        $pedido->refresh();
        $salida = ['version_pedido' => $pedido->version, 'operacion' => 'paquete_y_suelta_00001', 'motivo' => 'Un paquete declarado y una unidad suelta salen juntos.',
            'paquetes' => [$foto['agrupaciones'][0]['identidad']], 'detalles' => [['id' => $detalle->id, 'cantidad' => 3]]];
        $id = $servicio->despachar($pedido, $salida, $e['usuario']);
        $this->assertSame($id, $servicio->despachar($pedido, $salida, $e['usuario']));
        $distribucion = json_decode(DB::table('pedido_despachos')->where('id', $id)->value('distribucion'), true);
        $this->assertSame($foto['agrupaciones'][0]['identidad'], $distribucion['paquetes'][0]['identidad']);
        $this->assertSame(1, $distribucion['sueltas'][$e['presentacion']->id]);
        $enlace = (array) DB::table('pedido_despacho_agrupaciones')->where('despacho_id', $id)->sole();
        unset($enlace['id']);
        try {
            DB::table('pedido_despacho_agrupaciones')->insert($enlace);
            $this->fail('El índice único debe rechazar un segundo ingreso del mismo paquete físico.');
        } catch (QueryException $excepcion) {
            $this->assertSame('23000', (string) $excepcion->getCode());
        }
        $this->assertDatabaseCount('pedido_despacho_agrupaciones', 1);
        try {
            DB::table('movimientos_inventario')->insert(['despacho_unidades_id' => $id, 'presentacion_producto_id' => $e['skus'][1]->id, 'usuario_id' => $e['usuario']->id,
                'origen_tipo' => null, 'origen_id' => null, 'tipo' => 'salida', 'cantidad' => 1, 'stock_anterior' => 10, 'stock_nuevo' => 9, 'motivo' => 'Intento inválido con origen NULL.', 'created_at' => now(), 'updated_at' => now()]);
            $this->fail('NULL no debe eludir la integridad del origen del despacho.');
        } catch (QueryException $excepcion) {
            $this->assertStringContainsString('origen', $excepcion->getMessage());
        }
        $pedido->refresh();
        $salida['version_pedido'] = $pedido->version;
        $salida['operacion'] = 'paquete_repetido_00002';
        try {
            $servicio->despachar($pedido, $salida, $e['usuario']);
            $this->fail('Un paquete físico no sale dos veces.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('paquetes', $excepcion->errors());
        }
        $this->assertDatabaseCount('pedido_despachos', 1);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 7]);
        $salida['operacion'] = 'segundo_paquete_00003';
        $salida['paquetes'] = [$foto['agrupaciones'][1]['identidad']];
        $servicio->despachar($pedido, $salida, $e['usuario']);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 4]);
        $this->assertSame('despachado', $pedido->fresh()->estado);
        $this->get(route('cumplimiento.show', $pedido))->assertOk()->assertSee('Salidas físicas registradas');
    }

    public function test_un_paquete_despachado_no_se_recompone_mediante_edicion_comercial(): void
    {
        $e = $this->escenarioSurtido(10);
        $datos = $this->entrada($e, 6);
        $datos['agrupaciones'] = [['capacidad' => 4, 'componentes' => [['sku_id' => $e['presentacion']->id, 'cantidad' => 2]]]];
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id];
        $detalle = $pedido->detalles()->sole();
        $pedido->update(['estado' => 'en_preparacion']);
        $pedido->refresh();
        $servicio = app(AtenderPedidoUnidades::class);
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 6, 'motivo' => 'Preparación real de seis unidades para el pedido.'], $e['usuario']);
        $pedido->refresh();
        $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => 'paquete_inmutable_0001', 'motivo' => 'Se despacha el paquete expresamente incompleto.', 'paquetes' => [$foto['agrupaciones'][0]['identidad']], 'detalles' => [['id' => $detalle->id, 'cantidad' => 2]]], $e['usuario']);
        $datos['detalles'][0]['id'] = $detalle->id;
        $datos['_version'] = $pedido->fresh()->version;
        $datos['motivo'] = 'Intento de modificar la capacidad de un paquete que ya salió.';
        $datos['agrupaciones'] = $foto['agrupaciones'];
        $datos['agrupaciones'][0]['capacidad'] = 5;
        try {
            app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario'], $pedido);
            $this->fail('No debe reescribir el paquete físico.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('agrupaciones', $excepcion->errors());
        }
        $this->assertDatabaseCount('pedido_versiones', 1);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 8]);
    }

    public function test_sueltas_despachadas_no_pueden_reaparecer_dentro_de_otro_paquete(): void
    {
        $e = $this->escenarioSurtido(6);
        $datos = $this->entrada($e, 6);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $detalle = $pedido->detalles()->sole();
        $pedido->update(['estado' => 'en_preparacion']);
        $pedido->refresh();
        $servicio = app(AtenderPedidoUnidades::class);
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 6, 'motivo' => 'Se confirman las unidades físicamente preparadas.'], $e['usuario']);
        $pedido->refresh();
        $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => 'sueltas_previas_00001', 'motivo' => 'Salen tres unidades sueltas del pedido.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 3]]], $e['usuario']);
        $datos['detalles'][0]['id'] = $detalle->id;
        $datos['_version'] = $pedido->fresh()->version;
        $datos['motivo'] = 'Intento de reagrupar unidades que ya salieron sueltas.';
        $datos['agrupaciones'] = [['capacidad' => 6, 'componentes' => [['sku_id' => $e['presentacion']->id, 'cantidad' => 6]]]];
        try {
            app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario'], $pedido);
            $this->fail('No se reutilizan físicamente unidades ya despachadas.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('agrupaciones', $excepcion->errors());
        }
        $this->assertDatabaseCount('pedido_versiones', 1);
    }

    public function test_espera_se_registra_con_motivo_y_la_produccion_posterior_no_se_inventa(): void
    {
        $e = $this->escenarioSurtido(32);
        $datos = $this->entrada($e);
        $datos['detalles'][0]['cantidad_espera'] = 22;
        try {
            app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
            $this->fail('La espera requiere una decisión motivada.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('motivo', $excepcion->errors());
        }
        $datos['motivo'] = 'Se dejan veintidós unidades en espera sin prometer reposición.';
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $detalle = $pedido->detalles()->sole();
        $pedido->update(['estado' => 'en_preparacion']);
        $pedido->refresh();
        $servicio = app(AtenderPedidoUnidades::class);
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 32, 'incidencia' => 'insumos_quimicos', 'motivo' => 'Se agotaron insumos químicos; solo existen treinta y dos unidades.'], $e['usuario']);
        $pedido->refresh();
        $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => 'parcial_con_espera_001', 'motivo' => 'Salen treinta y dos; las otras veintidós siguen en espera.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 32]]], $e['usuario']);
        $this->assertSame('despacho_parcial', $pedido->fresh()->estado);
        $this->assertSame(22, $servicio->pendientes($pedido));
        $this->assertDatabaseCount('envasados', 0);
        $datos['_version'] = $pedido->fresh()->version;
        $datos['detalles'][0]['id'] = $detalle->id;
        $datos['detalles'][0]['cantidad_espera'] = 0;
        $datos['motivo'] = 'El personal confirmó que ahora puede producir el resto; se retira la espera.';
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario'], $pedido);
        $envasar = ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => $e['configuracion']->version, 'pedido_id' => $pedido->id, 'cantidad' => 22, '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->post(route('envasado.previsualizar'), $envasar)->assertOk();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 0]);
        $this->post(route('envasado.store'), $envasar)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('envasado.store'), $envasar)->assertRedirect();
        $this->assertDatabaseCount('envasados', 1);
        $this->assertSame('78.000', $e['insumo']->fresh()->cantidad_actual);
        $pedido->refresh();
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 22, 'motivo' => 'Se verificó el envasado físico del remanente de veintidós unidades.'], $e['usuario']);
        $pedido->refresh();
        $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => 'resto_confirmado_0002', 'motivo' => 'Salida del remanente realmente producido y preparado.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 22]]], $e['usuario']);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 0]);
        $this->assertSame('78.000', $e['insumo']->fresh()->cantidad_actual);
        $this->post(route('cumplimiento.entregar', $pedido))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('entregado', $pedido->fresh()->estado);
    }

    public function test_la_base_rechaza_componentes_que_pertenecen_a_otro_pedido(): void
    {
        $e = $this->escenarioSurtido(10);
        $datos = $this->entrada($e, 4);
        $datos['agrupaciones'] = [['capacidad' => 4, 'componentes' => [['sku_id' => $e['presentacion']->id, 'cantidad' => 4]]]];
        $primero = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $segundo = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $grupo = DB::table('pedido_agrupaciones')->where('pedido_id', $segundo->id)->sole();
        try {
            DB::table('pedido_agrupacion_detalles')->insert(['agrupacion_id' => $grupo->id, 'pedido_detalle_id' => $primero->detalles()->sole()->id, 'cantidad' => 1]);
            $this->fail('Los componentes no pueden cruzar pedidos aunque ambos identificadores existan.');
        } catch (QueryException $excepcion) {
            $this->assertStringContainsString('componente no pertenece', $excepcion->getMessage());
        }
        $this->assertDatabaseCount('pedido_agrupacion_detalles', 2);
    }

    public function test_lista_distingue_espera_preparacion_y_faltante_activo_sin_sumar_stock(): void
    {
        $e = $this->escenarioSurtido(32);
        $datos = $this->entrada($e);
        $datos['detalles'][0]['cantidad_espera'] = 22;
        $datos['motivo'] = 'Se dejan veintidós unidades en espera por falta de insumos.';
        $pedido = app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        app(AtenderPedidoUnidades::class)->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $pedido->detalles()->sole()->id, 'cantidad' => 32, 'motivo' => 'Se comprobaron treinta y dos unidades físicamente listas.'], $e['usuario']);
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertViewHas('filas', function ($filas): bool {
            $fila = $filas->sole();

            return $fila['total_unidades'] === 54 && $fila['en_espera'] === 22 && $fila['preparadas'] === 32
                && $fila['stock_disponible'] === 32 && $fila['envasadas'] === 0 && $fila['faltante'] === 0
                && str_contains($fila['cobertura'], 'la atención no está completa');
        });
        $this->get(route('cumplimiento.show', $pedido))->assertOk()->assertSee($datos['motivo']);
        $this->get(route('seguimiento.show', $pedido))->assertOk()->assertSee($datos['motivo']);
        $filasIndicadores = iterator_to_array(app(FilasIndicadores::class)->filas(FiltrosIndicadores::validar(['reporte' => 'preparacion', 'pedido_id' => $pedido->id])));
        $this->assertCount(1, $filasIndicadores);
        $this->assertSame(54, $filasIndicadores[0]['solicitadas']);
        $this->assertSame(22, $filasIndicadores[0]['unidades_en_espera']);
        $this->assertSame(32, $filasIndicadores[0]['unidades_activas']);
        $this->assertSame(0, $filasIndicadores[0]['faltante_conocido']);
        $this->assertDatabaseCount('envasados', 0);
    }

    public function test_agrupaciones_json_requiere_listas_en_lugar_de_claves_arbitrarias(): void
    {
        $e = $this->escenarioSurtido();
        $datos = $this->entrada($e, 2);
        $datos['agrupaciones'] = ['nombre' => ['capacidad' => 2, 'componentes' => [['sku_id' => $e['presentacion']->id, 'cantidad' => 2]]]];
        try {
            app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
            $this->fail('La estructura debe ser una lista numerada de paquetes.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('agrupaciones', $excepcion->errors());
        }
        $this->assertDatabaseCount('pedidos', 0);
        $datos['agrupaciones_json'] = json_encode($datos['agrupaciones'], JSON_THROW_ON_ERROR);
        $this->from(route('pedidos.create'))->post(route('pedidos.store'), $datos)->assertRedirect(route('pedidos.create'))->assertSessionHasErrors('agrupaciones');
        $this->get(route('pedidos.create'))->assertOk()->assertViewHas('agrupacionesIniciales', []);
    }

    public static function incidenciasFisicas(): array
    {
        return ['líquido no disponible' => ['liquido'], 'envases insuficientes' => ['envases'],
            'etiquetas o tapas' => ['etiquetas_tapas'], 'insumos químicos' => ['insumos_quimicos'],
            'tiempo insuficiente' => ['tiempo'], 'otra incidencia' => ['otro']];
    }

    #[DataProvider('incidenciasFisicas')]
    public function test_incidencia_con_cero_preparado_no_inventa_liquido_ni_produccion(string $incidencia): void
    {
        $e = $this->escenarioSurtido(0);
        $pedido = app(GestionarPedidoUnidades::class)->guardar($this->entrada($e), $e['usuario']);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        $this->post(route('pedidos.unidades.preparar', $pedido), ['version_pedido' => $pedido->version,
            'pedido_detalle_id' => $pedido->detalles()->sole()->id, 'cantidad' => 0, 'incidencia' => $incidencia,
            'motivo' => 'El personal comprobó físicamente que todavía no puede preparar unidades.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('pedido_preparaciones', ['pedido_id' => $pedido->id, 'cantidad' => 0, 'incidencia' => $incidencia]);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 0]);
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame(54, app(AtenderPedidoUnidades::class)->pendientes($pedido));
    }
}
