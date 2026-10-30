<?php

namespace Tests\Feature;

use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Services\AtenderPedidoUnidades;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarPedidoUnidades;
use App\Services\TokenOperacion;
use Database\Seeders\SurtidosPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\VentaUnidadesEscenario;

/** Regresiones de las agrupaciones que sustituyen la modalidad comercial retirada. */
class SurtidosTest extends TestCase
{
    use RefreshDatabase, VentaUnidadesEscenario;

    private function registrar(array $e, array $cantidades = [7, 11, 6], ?array $grupos = null): Pedido
    {
        return $this->registrarUnidades($e, $cantidades, $grupos ?? $this->agrupacionesUnidades($e));
    }

    private function versionEntrega(Pedido $pedido): int
    {
        return app(ControlEntregaPedido::class)->resumen($pedido->fresh())['version'];
    }

    private function editar(Pedido $pedido, array $e, array $grupos): Pedido
    {
        $entrada = ['cliente_id' => $pedido->cliente_id, 'fecha_pedido' => $pedido->fecha_pedido->toDateString(), 'fecha_entrega' => $pedido->fecha_entrega->toDateString(), 'condicion_pago' => $pedido->condicion_pago,
            '_version' => $pedido->version, 'motivo' => 'Revisión logística auditada sin modificar hechos físicos.',
            'detalles' => $pedido->detalles()->get()->map(fn ($d): array => ['id' => $d->id, 'presentacion_producto_id' => $d->presentacion_producto_id, 'cantidad' => $d->cantidad])->all(), 'agrupaciones' => $grupos];

        return app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario'], $pedido);
    }

    public function test_agrupacion_no_crea_productos_recetas_modalidades_ni_inventario(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $this->assertDatabaseCount('productos', 1);
        $this->assertDatabaseCount('presentaciones_producto', 3);
        $this->assertDatabaseCount('formatos_surtidos', 0);
        $this->assertDatabaseCount('pedido_paquetes_surtidos', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame('48.00', $pedido->total);
    }

    public function test_agrupacion_rechaza_capacidades_fisicas_incompatibles(): void
    {
        $e = $this->escenarioUnidades();
        $e['skus'][1]->update(['contenido' => 2]);
        $this->expectException(ValidationException::class);
        $this->registrar($e);
    }

    public function test_revision_logistica_conserva_version_anterior_y_rechaza_version_obsoleta(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $grupos = $this->agrupacionesUnidades($e);
        $grupos[0]['capacidad'] = 15;
        $nuevo = $this->editar($pedido, $e, $grupos);
        try {
            $this->editar($pedido, $e, $grupos);
            $this->fail('La versión anterior no debe sobrescribir la nueva.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('version', $error->errors());
        }
        $this->assertSame('48.00', $nuevo->total);
        $this->assertDatabaseCount('pedido_versiones', 2);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_rutas_retiradas_no_habilitan_configuracion_ni_composicion_y_el_pedido_sigue_visible(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $this->get('/modalidades-surtidas')->assertNotFound();
        $this->post('/pedidos/'.$pedido->id.'/surtidos/'.$pedido->detalles()->first()->id, [])->assertNotFound();
        $this->get(route('pedidos.show', $pedido))->assertOk()->assertSee('Lavanda')->assertDontSee('surtidos.js');
        $this->assertDatabaseCount('pedido_agrupaciones', 2);
    }

    public function test_agrupaciones_despachadas_no_pueden_cambiarse_retroactivamente(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $this->despacharUnidades($pedido, $e);
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id];
        $grupos = $foto['agrupaciones'];
        $grupos[0]['capacidad']++;
        $pedido->refresh();
        $this->expectException(ValidationException::class);
        $this->editar($pedido, $e, $grupos);
    }

    public function test_edicion_conserva_identidad_y_precio_pactado_y_retira_sin_borrar_historial(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $detalle = $pedido->detalles()->first();
        $e['skus'][0]->update(['precio_unitario' => 99]);
        $pedido = $this->editar($pedido, $e, []);
        $this->assertSame($detalle->id, $pedido->detalles()->first()->id);
        $this->assertSame('48.00', $pedido->total);
        $this->assertSame('2.00', $pedido->detalles()->first()->precio_unitario);
        $entrada = $this->entradaUnidades($e, [0, 11, 6]);
        $entrada['cliente_id'] = $pedido->cliente_id;
        foreach ($pedido->detalles()->get() as $i => $d) {
            $entrada['detalles'][$i]['id'] = $d->id;
        }
        $entrada['_version'] = $pedido->version;
        $entrada['motivo'] = 'Retiro comercial auditado sin borrar versiones anteriores.';
        app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario'], $pedido);
        $this->assertNotNull(DB::table('pedido_detalles')->where('id', $detalle->id)->value('retirado_en'));
        $this->assertDatabaseCount('pedido_versiones', 3);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_linea_anterior_sin_sku_no_inventa_demanda_y_exige_revision(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->pedidoEnvasado($e);
        $pedido->detalles()->delete();
        $pedido->detalles()->create(['tipo_linea' => 'surtido', 'presentacion_producto_id' => null, 'codigo_sku' => '', 'descripcion_producto' => 'Referencia anterior sin SKU',
            'modalidad' => 'empaque', 'cantidad' => 2, 'unidades_por_empaque' => 12, 'precio_unitario' => 24, 'importe' => 48]);
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertSee('Líneas anteriores pendientes de revisión')->assertViewHas('filas', fn ($filas): bool => $filas->isEmpty());
        $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertOk()->assertViewHas('puedeAtender', false);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_seeder_anterior_es_inocuo_y_conserva_cuentas_y_permisos_existentes(): void
    {
        $e = $this->escenarioUnidades();
        $e['usuario']->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => 'surtidos.configurar'])->id);
        $antes = [];
        foreach (['users', 'roles', 'permisos', 'rol_permiso'] as $tabla) {
            $antes[$tabla] = DB::table($tabla)->get()->toArray();
        }
        $this->seed(SurtidosPermisoSeeder::class);
        $this->seed(SurtidosPermisoSeeder::class);
        foreach ($antes as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
    }

    public function test_envasado_vinculado_usa_sku_sin_reservar_ni_descontar_otro_aroma(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $pedido->update(['estado' => 'confirmado']);
        $datos = ['presentacion_producto_id' => $e['skus'][0]->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => $e['configuracion']->version,
            'pedido_id' => $pedido->id, 'cantidad' => 3, '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->post(route('envasado.store'), $datos)->assertSessionHasNoErrors();
        $this->post(route('envasado.store'), $datos)->assertRedirect();
        $this->assertDatabaseCount('envasados', 1);
        $this->assertSame('97.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][0]->id, 'cantidad_actual' => 103]);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][1]->id, 'cantidad_actual' => 100]);
        $this->assertSame('confirmado', $pedido->fresh()->estado);
    }

    public function test_precio_por_unidad_conserva_centimos_sin_cobrar_otra_vez_las_agrupaciones(): void
    {
        $e = $this->escenarioUnidades();
        foreach ($e['skus'] as $sku) {
            $sku->update(['precio_unitario' => '0.01']);
        }
        $pedido = $this->registrar($e);
        $this->despacharUnidades($pedido, $e);
        $this->assertSame('0.24', $pedido->fresh()->total);
        $this->assertSame(24, array_sum(array_column(app(ControlEntregaPedido::class)->fotografia($pedido->fresh()), 'importe_centimos')));
        $this->assertSame(3, DB::table('pedido_despacho_lineas')->count());
    }

    public function test_mas_de_18_referencias_en_varios_despachos_admiten_resultados_y_retornos(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e, [35, 28, 21], []);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        $servicio = app(AtenderPedidoUnidades::class);
        foreach ($pedido->detalles()->get() as $d) {
            $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $d->id, 'cantidad' => $d->cantidad, 'motivo' => 'Unidades físicamente listas para siete despachos parciales.'], $e['usuario']);
            $pedido->refresh();
        }
        for ($n = 0; $n < 7; $n++) {
            $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => (string) Str::ulid(), 'motivo' => 'Despacho parcial de las unidades realmente preparadas.',
                'detalles' => $pedido->detalles()->get()->map(fn ($d, int $i): array => ['id' => $d->id, 'cantidad' => [5, 4, 3][$i]])->all()], $e['usuario']);
            $pedido->refresh();
        }
        $fisicas = app(ControlEntregaPedido::class)->resumen($pedido)['lineas'];
        $this->assertCount(21, $fisicas);
        $resultados = array_map(fn ($f): array => ['pedido_detalle_id' => $f['id'], 'aceptadas' => $f['despachadas'] - 1, 'rechazadas' => 1, 'motivo' => 'Rechazo de una unidad de cada salida física identificada.'], $fisicas);
        $this->post(route('cumplimiento.resultado-entrega', $pedido), ['version' => $this->versionEntrega($pedido), 'detalles' => array_values($resultados)])->assertSessionHasNoErrors();
        $retornos = array_map(fn ($f): array => ['pedido_detalle_id' => $f['id'], 'aptas' => 1, 'danadas' => 0, 'en_revision' => 0, 'no_recuperadas' => 0], $fisicas);
        $this->post(route('cumplimiento.retornos', $pedido), ['version' => $this->versionEntrega($pedido), 'observaciones' => 'Recepción física de las unidades rechazadas en cada despacho.', 'detalles' => array_values($retornos)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('entrega_pedido_detalles', 21);
        $this->assertDatabaseCount('retorno_pedido_detalles', 21);
        $this->assertSame('entrega_parcial', $pedido->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 21);
    }

    public function test_error_de_validacion_recupera_lineas_con_indices_discontinuos(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $fila = ['id' => $pedido->detalles()->first()->id, 'presentacion_producto_id' => $e['skus'][0]->id, 'cantidad' => 12];
        $respuesta = $this->withSession(['_old_input' => ['cliente_id' => $pedido->cliente_id, 'detalles' => [100000 => $fila]]])->get(route('pedidos.edit', $pedido));
        $respuesta->assertOk()->assertViewHas('lineas', fn ($lineas): bool => count($lineas) === 1 && (int) $lineas[0]['cantidad'] === 12);
        $respuesta->assertDontSee('paquetes_json');
    }

    public function test_dos_agrupaciones_independientes_no_crean_lineas_facturables_adicionales(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id];
        $this->assertSame('48.00', $pedido->total);
        $this->assertSame(24, (int) $pedido->detalles()->sum('cantidad'));
        $this->assertSame(3, $pedido->detalles()->count());
        $this->assertCount(2, $foto['agrupaciones']);
        $this->assertSame([5, 4, 3], array_column($foto['agrupaciones'][0]['componentes'], 'cantidad'));
        $this->assertSame([2, 7, 3], array_column($foto['agrupaciones'][1]['componentes'], 'cantidad'));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_agrupacion_opcional_no_inventa_demanda_y_sus_versiones_se_conservan(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e, [7, 11, 6], []);
        $this->assertDatabaseCount('pedido_agrupaciones', 0);
        $nuevo = $this->editar($pedido, $e, $this->agrupacionesUnidades($e));
        $this->assertSame(24, (int) $nuevo->detalles()->sum('cantidad'));
        $this->assertSame('48.00', $nuevo->total);
        $this->assertDatabaseCount('pedido_versiones', 2);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_agrupacion_incompleta_es_explicita_y_sobreasignar_no_deja_versiones_parciales(): void
    {
        $e = $this->escenarioUnidades();
        $grupos = $this->agrupacionesUnidades($e);
        $grupos[1]['componentes'][2]['cantidad'] = 2;
        $pedido = $this->registrar($e, [7, 11, 6], $grupos);
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id];
        $this->assertSame(11, $foto['agrupaciones'][1]['unidades']);
        $grupos[1]['componentes'][2]['cantidad'] = 4;
        try {
            $this->editar($pedido, $e, $grupos);
            $this->fail('No se permite superar las unidades físicas vendidas.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('agrupaciones', $error->errors());
        }
        $this->assertDatabaseCount('pedido_versiones', 1);
        $this->assertSame('48.00', $pedido->fresh()->total);
    }

    public function test_formulario_y_detalle_muestran_unidades_y_agrupaciones_sin_modalidad_retirada(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $this->get(route('pedidos.edit', $pedido))->assertOk()->assertSee('Pedidos por unidades físicas')->assertDontSee('surtidos.js');
        $this->get('/modalidades-surtidas')->assertNotFound();
        $this->get(route('pedidos.show', $pedido))->assertOk()->assertSee('Paquete 1')->assertSee('Paquete 2')->assertSee('Lavanda');
    }

    public function test_version_obsoleta_y_sku_ajeno_no_modifican_agrupaciones(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $this->editar($pedido, $e, $this->agrupacionesUnidades($e));
        try {
            $this->editar($pedido, $e, $this->agrupacionesUnidades($e));
            $this->fail('No se acepta una versión obsoleta.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('version', $error->errors());
        }
        $grupos = $this->agrupacionesUnidades($e);
        $grupos[0]['componentes'][0]['sku_id'] = 999999;
        try {
            $this->editar($pedido->fresh(), $e, $grupos);
            $this->fail('No se acepta un SKU ajeno a la venta.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('agrupaciones', $error->errors());
        }
        $this->assertDatabaseCount('pedido_versiones', 2);
    }

    public function test_finalizar_y_despachar_exigen_cantidades_fisicamente_preparadas(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $pedido->update(['estado' => 'en_preparacion']);
        $this->post(route('cumplimiento.finalizar-preparacion', $pedido))->assertSessionHasErrors('estado');
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_despachos', 0);
    }

    public function test_despacho_agrega_unidades_sueltas_y_agrupadas_sin_descuentos_duplicados(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e, [31, 11, 6]);
        $this->despacharUnidades($pedido, $e);
        foreach ([69, 89, 94] as $i => $stock) {
            $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][$i]->id, 'cantidad_actual' => $stock]);
        }
        $this->assertDatabaseCount('movimientos_inventario', 3);
        $this->assertDatabaseCount('pedido_despacho_agrupaciones', 2);
    }

    public function test_stock_insuficiente_no_deja_salidas_ni_descuentos_parciales(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e, [7, 11, 6], []);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        $servicio = app(AtenderPedidoUnidades::class);
        foreach ($pedido->detalles()->get() as $d) {
            $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $d->id, 'cantidad' => $d->cantidad, 'motivo' => 'Comprobación previa de las cantidades efectivamente listas.'], $e['usuario']);
            $pedido->refresh();
        }
        Inventario::where('presentacion_producto_id', $e['skus'][2]->id)->update(['cantidad_actual' => 5]);
        $antes = DB::table('inventarios')->get()->toArray();
        try {
            $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => (string) Str::ulid(), 'motivo' => 'Intento con stock ya insuficiente tras la preparación.',
                'detalles' => $pedido->detalles()->get()->map(fn ($d): array => ['id' => $d->id, 'cantidad' => $d->cantidad])->all()], $e['usuario']);
            $this->fail('La salida debe rechazarse antes de descontar cualquier SKU.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('inventario', $error->errors());
        }
        $this->assertEquals($antes, DB::table('inventarios')->get()->toArray());
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_despachos', 0);
    }

    public function test_rechazo_y_retorno_identifican_sku_y_reincorporan_solo_una_vez(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $this->despacharUnidades($pedido, $e);
        $pedido->refresh();
        $fisica = collect(app(ControlEntregaPedido::class)->resumen($pedido)['lineas'])->firstWhere('presentacion_id', $e['skus'][0]->id);
        $this->post(route('cumplimiento.resultado-entrega', $pedido), ['version' => $this->versionEntrega($pedido), 'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aceptadas' => 5, 'rechazadas' => 2, 'motivo' => 'Dos unidades del aroma identificado fueron rechazadas.']]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][0]->id, 'cantidad_actual' => 93]);
        $this->post(route('cumplimiento.retornos', $pedido), ['version' => $this->versionEntrega($pedido), 'observaciones' => 'Recepción física de una unidad apta y una dañada.', 'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aptas' => 1, 'danadas' => 1, 'en_revision' => 0, 'no_recuperadas' => 0]]])->assertSessionHasNoErrors();
        $retorno = DB::table('retorno_pedido_detalles')->sole();
        $datos = ['version' => $this->versionEntrega($pedido), 'retorno_detalle_id' => $retorno->id, 'cantidad' => 1, 'motivo' => 'Unidad físicamente recibida y apta para reincorporar.'];
        $this->post(route('cumplimiento.aprobar-retorno', $pedido), $datos)->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.aprobar-retorno', $pedido), $datos)->assertSessionHasErrors('version');
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['skus'][0]->id, 'cantidad_actual' => 94]);
        $this->assertSame(1, DB::table('movimientos_inventario')->where('origen_tipo', 'retorno_pedido')->count());
        $this->assertSame('48.00', $pedido->fresh()->total);
        $this->get(route('cumplimiento.show', $pedido))->assertOk()->assertSee('Lavanda');
    }

    public function test_lista_consolida_aromas_y_senala_espera_sin_ocultar_filtros(): void
    {
        $e = $this->escenarioUnidades();
        $entrada = $this->entradaUnidades($e);
        $entrada['detalles'][0]['cantidad_espera'] = 2;
        $entrada['motivo'] = 'Dos unidades quedan en espera por una incidencia comunicada.';
        $pedido = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);
        $pedido->update(['estado' => 'confirmado']);
        $r = $this->get(route('cumplimiento.lista-preparacion'))->assertOk();
        $this->assertSame(24, $r->viewData('filas')->sum('total_unidades'));
        $this->assertSame(2, $r->viewData('filas')->sum('en_espera'));
        $this->assertCount(3, $r->viewData('filas'));
        $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertOk()->assertViewHas('puedeAtender', true);
        $pedido->update(['estado' => 'despachado']);
        $this->get(route('cumplimiento.lista-preparacion'))->assertViewHas('filas', fn ($filas): bool => $filas->isEmpty());
    }

    public function test_impresion_incluye_agrupaciones_y_catalogo_no_reescribe_descripcion_ni_precio(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->registrar($e);
        $e['skus'][0]->variante->update(['valor' => 'Nombre nuevo']);
        $e['skus'][0]->update(['precio_unitario' => 99]);
        $this->get(route('pedidos.show', $pedido))->assertOk()->assertSee('Lavanda');
        $this->get(route('pedidos.edit', $pedido))->assertOk()->assertSee('2.00');
        $this->assertSame('48.00', $pedido->fresh()->total);
        $this->get(route('pedidos.imprimir-por-fecha', ['fecha_pedido' => today()->toDateString()]))->assertOk()->assertSee('Agrupaciones del pedido')->assertSee('Paquete 2')->assertSee('Lavanda')->assertDontSee('Anexo de composición');
    }
}
