<?php

namespace Tests\MySql;

use App\Models\MovimientoInventario;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use App\Services\AprobarRetornoPedido;
use App\Services\ControlEntregaPedido;
use App\Services\TokenOperacion;
use Carbon\CarbonImmutable;
use Database\Seeders\EntregaRetornoPermisoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\EnvasadoEscenario;

class EntregasRetornosTest extends PruebaEntregaMySql
{
    use EnvasadoEscenario;

    private function escenarioEntrega(bool $empaque = false, bool $fraccion = false, bool $despachar = true): array
    {
        $e = $this->escenarioEnvasado(100, 100);
        foreach (['seguimiento.ver', 'cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar', 'cumplimiento.incidencias.revisar', 'cumplimiento.incidencias.resolver', 'cumplimiento.incidencias.corregir'] as $nombre) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }
        $pedido = $this->pedidoEnvasado($e, $despachar ? 'listo_despacho' : 'despachado');
        $detalle = $pedido->detalles()->firstOrFail();
        if ($empaque) {
            $detalle->update(['modalidad' => 'empaque', 'cantidad' => $fraccion ? 1 : 2, 'unidades_por_empaque' => $fraccion ? 3 : 15, 'precio_unitario' => $fraccion ? 10 : 30, 'importe' => $fraccion ? 10 : 60]);
            $pedido->update(['total' => $fraccion ? 10 : 60]);
        }
        $this->actingAs($e['usuario']);
        if ($despachar) {
            $this->post(route('cumplimiento.despachar', $pedido))->assertRedirect();
        }

        return $e + ['pedido' => $pedido->fresh(), 'detalle' => $detalle->fresh()];
    }

    private function version(array $e): int
    {
        return (int) DB::table('incidencias_entrega')->where('pedido_id', $e['pedido']->id)->value('version');
    }

    private function resultado(array $e, int $aceptadas, int $rechazadas, string $ruta = 'cumplimiento.resultado-entrega'): TestResponse
    {
        return $this->post(route($ruta, $e['pedido']), ['version' => $this->version($e), 'observaciones' => 'Resultado informado durante la visita.', 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'aceptadas' => $aceptadas, 'rechazadas' => $rechazadas, 'motivo' => 'Cliente informa que no solicitó estas unidades.']]]);
    }

    private function retorno(array $e, int $aptas, int $danadas = 0, int $revision = 0, int $perdidas = 0): TestResponse
    {
        return $this->post(route('cumplimiento.retornos', $e['pedido']), ['version' => $this->version($e), 'observaciones' => 'Conteo físico documentado en almacén.', 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'aptas' => $aptas, 'danadas' => $danadas, 'en_revision' => $revision, 'no_recuperadas' => $perdidas]]]);
    }

    public static function rolesEntrega(): array
    {
        return [
            'producción' => ['Producción/Reparto', false, false],
            'comercial' => ['Operaciones Comerciales', true, false],
            'administrador' => ['Administrador', true, true],
        ];
    }

    #[DataProvider('rolesEntrega')]
    public function test_roles_reales_registran_resultados_y_retornos_con_aprobacion_y_rectificacion_separadas(string $nombre, bool $aprueba, bool $rectifica): void
    {
        $e = $this->escenarioEntrega();
        foreach (['Administrador', 'Operaciones Comerciales', 'Producción/Reparto'] as $rol) {
            Role::create(['nombre' => $rol, 'es_sistema' => true]);
        }
        $this->seed(EntregaRetornoPermisoSeeder::class);
        $rol = Role::where('nombre', $nombre)->firstOrFail();
        $rol->permisos()->syncWithoutDetaching(Permiso::whereIn('nombre', ['pedidos.ver', 'cumplimiento.entregar'])->pluck('id'));
        $e['usuario']->update(['role_id' => $rol->id]);
        $e['usuario']->unsetRelation('role');
        $this->actingAs($e['usuario']);
        $this->resultado($e, 3, 2)->assertRedirect();
        $this->retorno($e, 2)->assertRedirect();
        $pagina = $this->get(route('cumplimiento.show', $e['pedido']))->assertOk();
        if ($aprueba) {
            $pagina->assertSee('Aprobar reincorporación');
        } else {
            $pagina->assertDontSee('Aprobar reincorporación')->assertDontSee('Rectificación administrativa autorizada');
        }
        $revision = $this->post(route('cumplimiento.incidencia.revisar', $e['pedido']), ['version' => $this->version($e), 'motivo' => 'Comprobación comercial documentada.']);
        $aprueba ? $revision->assertRedirect() : $revision->assertForbidden();
        $aprobacion = $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), ['version' => $this->version($e), 'retorno_detalle_id' => DB::table('retorno_pedido_detalles')->value('id'), 'cantidad' => 1, 'motivo' => 'Producto recibido y apto verificado.']);
        $aprueba ? $aprobacion->assertRedirect() : $aprobacion->assertForbidden();
        $correccion = $this->resultado($e, -1, 0, 'cumplimiento.rectificar-entrega');
        $rectifica ? $correccion->assertRedirect() : $correccion->assertForbidden();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => $aprueba ? 96 : 95]);
        $this->assertSame($aprueba ? 1 : 0, DB::table('movimientos_inventario')->where('origen_tipo', 'retorno_pedido')->count());
        $this->assertSame($rectifica ? 2 : 3, app(ControlEntregaPedido::class)->resumen($e['pedido'])['lineas'][$e['detalle']->id]['aceptadas']);
    }

    public function test_resultados_sucesivos_no_alteran_pedido_precios_ni_existencias(): void
    {
        $e = $this->escenarioEntrega();
        $antes = $e['detalle']->getAttributes();
        $this->resultado($e, 2, 1)->assertRedirect();
        $this->assertSame('entrega_pendiente', $e['pedido']->fresh()->estado);
        $this->resultado($e, 1, 1)->assertRedirect();
        $this->assertSame('entrega_parcial', $e['pedido']->fresh()->estado);
        $this->assertSame($antes, $e['detalle']->fresh()->getAttributes());
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 95]);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $r = app(ControlEntregaPedido::class)->resumen($e['pedido']);
        $this->assertSame(3, $r['lineas'][$e['detalle']->id]['aceptadas']);
        $this->assertSame(2, $r['lineas'][$e['detalle']->id]['rechazadas']);
        $this->assertSame(0, $r['lineas'][$e['detalle']->id]['pendientes']);
    }

    public function test_rechazo_total_retorno_y_aprobaciones_parciales_no_duplican_stock(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 0, 5)->assertRedirect();
        $this->assertSame('rechazado', $e['pedido']->fresh()->estado);
        $this->retorno($e, 2)->assertRedirect();
        $this->retorno($e, 2, 1)->assertRedirect();
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 95]);
        $retornos = DB::table('retorno_pedido_detalles')->orderBy('id')->get();
        foreach ([[$retornos[0]->id, 1], [$retornos[0]->id, 1], [$retornos[1]->id, 2]] as [$id, $cantidad]) {
            $datos = ['version' => $this->version($e), 'retorno_detalle_id' => $id, 'cantidad' => $cantidad, 'motivo' => 'Verificado físicamente, apto para reincorporar.', '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
            $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), $datos)->assertRedirect();
            $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), $datos)->assertRedirect();
        }
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 99]);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(3, DB::table('movimientos_inventario')->where('origen_tipo', 'retorno_pedido')->count());
        $this->assertSame(4, (int) DB::table('retorno_pedido_detalles')->sum('reincorporadas'));
        $this->assertSame('rechazado', $e['pedido']->fresh()->estado);
    }

    public function test_unidad_dentro_de_caja_y_cambio_catalogo_respetan_fotografia_historica(): void
    {
        $e = $this->escenarioEntrega(true);
        $e['presentacion']->update(['unidades_por_empaque' => 100]);
        $this->resultado($e, 29, 1)->assertRedirect();
        $r = app(ControlEntregaPedido::class)->resumen($e['pedido'])['lineas'][$e['detalle']->id];
        $this->assertSame(30, $r['despachadas']);
        $this->assertSame(5800, $r['importe_aceptado_centimos']);
        $this->assertSame(200, $r['importe_rechazado_centimos']);
        $this->assertSame(0, $r['importe_pendiente_centimos']);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 70]);
    }

    public function test_no_supera_despachado_ni_reduce_rechazo_ya_retornado(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 4, 2)->assertSessionHasErrors('detalles');
        $this->resultado($e, 3, 2)->assertRedirect();
        $this->retorno($e, 1)->assertRedirect();
        $this->resultado($e, 2, -2, 'cumplimiento.rectificar-entrega')->assertSessionHasErrors('detalles');
        $this->resultado($e, 1, -1, 'cumplimiento.rectificar-entrega')->assertRedirect();
        $this->assertSame(2, DB::table('entrega_pedido_detalles')->count());
    }

    public static function estadosResultado(): array
    {
        return ['pendiente' => [2, 1, 'entrega_pendiente'], 'parcial' => [2, 3, 'entrega_parcial'], 'rechazo' => [0, 5, 'rechazado']];
    }

    #[DataProvider('estadosResultado')]
    public function test_estados_aparecen_en_cumplimiento_seguimiento_y_filtros_sin_volver_a_preparacion(int $aceptadas, int $rechazadas, string $estado): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, $aceptadas, $rechazadas)->assertRedirect();
        $this->assertSame($estado, $e['pedido']->fresh()->estado);
        $this->get(route('cumplimiento.show', $e['pedido']))->assertOk()->assertSee('Aceptadas por el cliente')->assertSee('Rechazadas')->assertSee('Historial de incidencias y devoluciones');
        $this->get(route('seguimiento.show', $e['pedido']))->assertOk()->assertSee('Recibidas físicamente');
        $this->get(route('seguimiento.trazabilidad', $e['pedido']))->assertOk()->assertSee('Resultado informado durante la visita.');
        $this->get(route('seguimiento.historial', ['estado' => $estado]))->assertOk()->assertSee($e['pedido']->numero);
        $this->get(route('cumplimiento.index', ['estado' => $estado]))->assertOk()->assertSee($e['pedido']->numero);
        $this->get(route('pedidos.index', ['estado' => $estado]))->assertOk()->assertSee($e['pedido']->numero);
        $this->assertCount(0, $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->viewData('filas'));
        $this->artisan('inventario:revisar-despachos-previos')->expectsOutputToContain('Pedidos sin salida vinculada: 0')->assertSuccessful();
    }

    public function test_evaluacion_danados_perdidos_y_cierre_comercial_no_generan_stock_extra(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 0, 5)->assertRedirect();
        $this->retorno($e, 0, 1, 2, 2)->assertRedirect();
        $id = DB::table('retorno_pedido_detalles')->value('id');
        $this->post(route('cumplimiento.incidencia.resolver', $e['pedido']), ['version' => $this->version($e), 'motivo' => 'Se revisó el acuerdo comercial.'])->assertSessionHasErrors('resolucion');
        $this->post(route('cumplimiento.incidencia.clasificar', $e['pedido']), ['version' => $this->version($e), 'retorno_detalle_id' => $id, 'aptas' => 1, 'danadas' => 1, 'motivo' => 'Evaluación física concluida en almacén.'])->assertRedirect();
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 95]);
        $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), ['version' => $this->version($e), 'retorno_detalle_id' => $id, 'cantidad' => 2, 'motivo' => 'Autorización de reincorporación física.'])->assertSessionHasErrors('cantidad');
        $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), ['version' => $this->version($e), 'retorno_detalle_id' => $id, 'cantidad' => 1, 'motivo' => 'Autorización de reincorporación física.'])->assertRedirect();
        $this->post(route('cumplimiento.incidencia.revisar', $e['pedido']), ['version' => $this->version($e), 'motivo' => 'Diferencia económica revisada sin modificar pagos.'])->assertRedirect();
        $this->post(route('cumplimiento.incidencia.resolver', $e['pedido']), ['version' => $this->version($e), 'motivo' => 'Acuerdo comercial documentado con el cliente.'])->assertRedirect();
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 96]);
        $this->assertSame('rechazado', $e['pedido']->fresh()->estado);
        $this->retorno($e, 1)->assertSessionHasErrors('version');
        $this->post(route('cumplimiento.incidencia.reabrir', $e['pedido']), ['version' => $this->version($e), 'motivo' => 'Reapertura administrativa documentada.'])->assertRedirect();
        $this->assertNull(DB::table('incidencias_entrega')->value('resuelto_en'));
        $this->assertTrue(DB::table('incidencia_entrega_eventos')->where('tipo', 'incidencia_resolver')->exists());
        $this->assertTrue(DB::table('auditoria')->where('accion', 'retorno_aprobado')->where('usuario_id', $e['usuario']->id)->exists());
    }

    public function test_permiso_insuficiente_y_version_obsoleta_no_reincorporan(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 0, 5)->assertRedirect();
        $this->retorno($e, 5)->assertRedirect();
        $id = DB::table('retorno_pedido_detalles')->value('id');
        $datos = ['version' => 0, 'retorno_detalle_id' => $id, 'cantidad' => 1, 'motivo' => 'Verificado físicamente en almacén.'];
        $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), $datos)->assertSessionHasErrors('version');
        $e['usuario']->role->permisos()->detach(Permiso::where('nombre', 'cumplimiento.retornos.aprobar')->value('id'));
        $this->actingAs($e['usuario']->fresh());
        $datos['version'] = $this->version($e);
        $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), $datos)->assertForbidden();
        $e['usuario']->role->permisos()->detach(Permiso::where('nombre', 'cumplimiento.incidencias.corregir')->value('id'));
        $this->actingAs($e['usuario']->fresh());
        $this->resultado($e, 1, -1, 'cumplimiento.rectificar-entrega')->assertForbidden();
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 95]);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_fallo_de_movimiento_revierte_stock_aprobacion_evento_y_version(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 0, 5)->assertRedirect();
        $this->retorno($e, 5)->assertRedirect();
        $version = $this->version($e);
        $datos = ['version' => $version, 'retorno_detalle_id' => DB::table('retorno_pedido_detalles')->value('id'), 'cantidad' => 2, 'motivo' => 'Verificado físicamente en almacén.'];
        MovimientoInventario::creating(function ($movimiento): void {
            if ($movimiento->origen_tipo === 'retorno_pedido') {
                throw new \RuntimeException('Fallo simulado de inventario');
            }
        });
        try {
            app(AprobarRetornoPedido::class)->ejecutar($e['pedido'], $datos, $e['usuario']);
            $this->fail('Se esperaba el fallo simulado.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fallo simulado de inventario', $error->getMessage());
        }
        $this->assertSame($version, $this->version($e));
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 95]);
        $this->assertSame(0, (int) DB::table('retorno_pedido_detalles')->sum('reincorporadas'));
        $this->assertFalse(DB::table('incidencia_entrega_eventos')->where('tipo', 'retorno_aprobado')->exists());
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_seeder_solo_agrega_permisos_aprobados_sin_reemplazar_usuarios_ni_permisos(): void
    {
        $roles = [];
        $anterior = Permiso::create(['nombre' => 'permiso.anterior', 'descripcion' => 'Conservar']);
        foreach (['Administrador', 'Operaciones Comerciales', 'Producción/Reparto'] as $nombre) {
            $rol = Role::create(['nombre' => $nombre]);
            $rol->permisos()->attach($anterior->id);
            $roles[$nombre] = $rol;
        }
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'permisos@entregas.test', 'password' => 'ClaveSegura123', 'status' => true, 'role_id' => $roles['Administrador']->id]);
        $antes = $usuario->fresh()->getAttributes();
        $this->seed(EntregaRetornoPermisoSeeder::class);
        $this->seed(EntregaRetornoPermisoSeeder::class);
        $this->assertDatabaseCount('permisos', 7);
        $this->assertDatabaseCount('roles', 3);
        $this->assertSame($antes, $usuario->fresh()->getAttributes());
        foreach (['Administrador' => 6, 'Operaciones Comerciales' => 5, 'Producción/Reparto' => 2] as $nombre => $cantidad) {
            $this->assertSame($cantidad, $roles[$nombre]->permisos()->count());
            $this->assertTrue($roles[$nombre]->tienePermiso('permiso.anterior'));
            $this->assertTrue($roles[$nombre]->tienePermiso('cumplimiento.retornos.registrar'));
        }
        $this->assertFalse($roles['Producción/Reparto']->tienePermiso('cumplimiento.retornos.aprobar'));
        $this->assertFalse($roles['Operaciones Comerciales']->tienePermiso('cumplimiento.incidencias.corregir'));
    }

    public function test_lineas_ajenas_duplicadas_y_cantidades_invalidas_se_rechazan(): void
    {
        $e = $this->escenarioEntrega();
        $otra = $this->pedidoEnvasado($e)->detalles()->firstOrFail();
        $datos = ['version' => $this->version($e), 'detalles' => [['pedido_detalle_id' => $otra->id, 'aceptadas' => 1, 'rechazadas' => 0]]];
        $this->post(route('cumplimiento.resultado-entrega', $e['pedido']), $datos)->assertSessionHasErrors('detalles');
        $datos['detalles'][0]['pedido_detalle_id'] = $e['detalle']->id;
        $datos['detalles'][1] = $datos['detalles'][0];
        $this->post(route('cumplimiento.resultado-entrega', $e['pedido']), $datos)->assertSessionHasErrors('detalles.0.pedido_detalle_id');
        unset($datos['detalles'][1]);
        foreach ([-1, '1.5', 'texto'] as $cantidad) {
            $datos['detalles'][0]['aceptadas'] = $cantidad;
            $this->post(route('cumplimiento.resultado-entrega', $e['pedido']), $datos)->assertSessionHasErrors('detalles.0.aceptadas');
        }
        $this->assertDatabaseCount('entregas_pedido', 0);
    }

    public function test_saldo_economico_conserva_centimos_al_fraccionar_un_empaque(): void
    {
        $e = $this->escenarioEntrega(true, true);
        $this->resultado($e, 1, 1)->assertRedirect();
        $linea = app(ControlEntregaPedido::class)->resumen($e['pedido'])['lineas'][$e['detalle']->id];
        $this->assertSame(333, $linea['importe_aceptado_centimos']);
        $this->assertSame(333, $linea['importe_rechazado_centimos']);
        $this->assertSame(334, $linea['importe_pendiente_centimos']);
    }

    public function test_despacho_previo_sin_salida_permite_documentar_pero_no_devolver_stock(): void
    {
        $e = $this->escenarioEntrega(despachar: false);
        $this->resultado($e, 0, 5)->assertRedirect();
        $this->retorno($e, 5)->assertRedirect();
        $id = DB::table('retorno_pedido_detalles')->value('id');
        $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), ['version' => $this->version($e), 'retorno_detalle_id' => $id, 'cantidad' => 1, 'motivo' => 'Solicita verificar el despacho histórico.'])->assertSessionHasErrors('cantidad');
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 100]);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_empaque_historico_sin_tamano_no_inventa_unidades_del_catalogo_actual(): void
    {
        $e = $this->escenarioEntrega(empaque: true, despachar: false);
        $e['detalle']->update(['unidades_por_empaque' => null]);
        $this->resultado($e, 0, 5)->assertSessionHasErrors('detalles');
        $this->assertDatabaseCount('entregas_pedido', 0);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 100]);
    }

    public function test_despacho_nuevo_fotografia_respaldo_de_catalogo_si_linea_antigua_es_null(): void
    {
        $e = $this->escenarioEntrega(empaque: true, despachar: false);
        $e['detalle']->update(['unidades_por_empaque' => null]);
        $e['pedido']->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $e['pedido']))->assertRedirect();
        $e['presentacion']->update(['unidades_por_empaque' => 15]);
        $this->resultado($e, 23, 1)->assertRedirect();
        $linea = app(ControlEntregaPedido::class)->resumen($e['pedido'])['lineas'][$e['detalle']->id];
        $this->assertSame(24, $linea['despachadas']);
        $this->assertTrue($linea['verificada']);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 76]);
    }

    public function test_una_entrega_historica_sin_desglose_no_se_presenta_como_nuevas_cantidades_pendientes(): void
    {
        $e = $this->escenarioEntrega(despachar: false);
        $e['pedido']->update(['estado' => 'entregado']);
        $pedidoAnterior = $e['pedido']->fresh()->getAttributes();
        $detalleAnterior = $e['detalle']->fresh()->getAttributes();
        $this->get(route('cumplimiento.show', $e['pedido']))->assertOk()->assertSee('Entrega histórica sin desglose')->assertDontSee('Registrar cantidades aceptadas');
        $this->resultado($e, 0, 1, 'cumplimiento.rectificar-entrega')->assertSessionHasErrors('detalles');
        $this->retorno($e, 1)->assertSessionHasErrors('detalles');
        $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), ['version' => $this->version($e), 'retorno_detalle_id' => 999999, 'cantidad' => 1, 'motivo' => 'Intento de reincorporación sin recepción.'])->assertSessionHasErrors('cantidad');
        $this->assertSame($pedidoAnterior, $e['pedido']->fresh()->getAttributes());
        $this->assertSame($detalleAnterior, $e['detalle']->fresh()->getAttributes());
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 100]);
        $this->assertDatabaseCount('entregas_pedido', 0);
        $this->assertDatabaseCount('retornos_pedido', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_resultado_pendiente_sin_cantidades_y_doble_envio_del_resultado(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 0, 0)->assertRedirect();
        $this->assertSame('entrega_pendiente', $e['pedido']->fresh()->estado);
        $datos = ['version' => $this->version($e), '_token_operacion' => TokenOperacion::emitir($e['usuario']->id), 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'aceptadas' => 5, 'rechazadas' => 0]]];
        $this->post(route('cumplimiento.resultado-entrega', $e['pedido']), $datos)->assertRedirect();
        $this->post(route('cumplimiento.resultado-entrega', $e['pedido']), $datos)->assertRedirect();
        $this->assertSame('entregado', $e['pedido']->fresh()->estado);
        $this->assertDatabaseCount('entregas_pedido', 2);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_dos_lineas_del_mismo_sku_con_cajas_distintas_conservan_limites_y_retornos_por_linea(): void
    {
        $e = $this->escenarioEntrega(empaque: true, despachar: false);
        $segunda = $e['pedido']->detalles()->create(['presentacion_producto_id' => $e['presentacion']->id, 'codigo_sku' => $e['presentacion']->codigo_sku, 'descripcion_producto' => 'Misma presentación, otro empaque', 'modalidad' => 'empaque', 'cantidad' => 1, 'unidades_por_empaque' => 12, 'precio_unitario' => 24, 'importe' => 24]);
        $e['pedido']->update(['estado' => 'listo_despacho', 'total' => 84]);
        $this->post(route('cumplimiento.despachar', $e['pedido']))->assertRedirect();
        $this->resultado($e, 29, 1)->assertRedirect();
        $this->post(route('cumplimiento.resultado-entrega', $e['pedido']), ['version' => $this->version($e), 'detalles' => [['pedido_detalle_id' => $segunda->id, 'aceptadas' => 10, 'rechazadas' => 2, 'motivo' => 'Cliente rechaza dos unidades de esta línea.']]])->assertRedirect();
        $this->assertSame('entrega_parcial', $e['pedido']->fresh()->estado);
        $this->get(route('cumplimiento.show', $e['pedido']))->assertOk()->assertSee('name="detalles[1][aptas]"', false);
        $filas = [];
        foreach ([[$e['detalle']->id, 1], [$segunda->id, 2]] as [$id, $cantidad]) {
            $filas[] = ['pedido_detalle_id' => $id, 'aptas' => $cantidad, 'danadas' => 0, 'en_revision' => 0, 'no_recuperadas' => 0];
        }
        $this->post(route('cumplimiento.retornos', $e['pedido']), ['version' => $this->version($e), 'observaciones' => 'Ambas líneas fueron recibidas físicamente.', 'detalles' => $filas])->assertRedirect();
        foreach (DB::table('retorno_pedido_detalles')->orderBy('id')->get() as $retorno) {
            $this->post(route('cumplimiento.aprobar-retorno', $e['pedido']), ['version' => $this->version($e), 'retorno_detalle_id' => $retorno->id, 'cantidad' => $retorno->aptas, 'motivo' => 'Conteo y estado apto verificados por línea.'])->assertRedirect();
        }
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 61]);
        $this->assertDatabaseCount('movimientos_inventario', 3);
        $this->assertSame('84.00', $e['pedido']->fresh()->total);
    }

    public function test_fecha_informada_se_normaliza_a_lima_y_distingue_fecha_de_registro(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 08:00:00', 'America/Lima'));
        $e = $this->escenarioEntrega();
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00', 'America/Lima'));
        $this->post(route('cumplimiento.resultado-entrega', $e['pedido']), ['version' => 0, 'ocurrido_en' => '2026-10-24T10:30:00-03:00', 'detalles' => [['pedido_detalle_id' => $e['detalle']->id, 'aceptadas' => 5, 'rechazadas' => 0]]])->assertRedirect();
        $this->assertDatabaseHas('entregas_pedido', ['usuario_id' => $e['usuario']->id, 'ocurrido_en' => '2026-10-24 08:30:00', 'created_at' => '2026-10-24 12:00:00']);
    }

    public function test_rectificar_entrega_completa_conserva_fecha_y_resultado_original_sin_devolver_stock(): void
    {
        $e = $this->escenarioEntrega();
        $this->resultado($e, 5, 0)->assertRedirect();
        $fechaOriginal = $e['pedido']->fresh()->entregado_en;
        $this->resultado($e, -1, 1, 'cumplimiento.rectificar-entrega')->assertRedirect();
        $this->assertSame('entrega_parcial', $e['pedido']->fresh()->estado);
        $this->assertSame($fechaOriginal, $e['pedido']->fresh()->entregado_en);
        $this->assertSame(5, (int) DB::table('entrega_pedido_detalles')->orderBy('id')->value('aceptadas'));
        $this->assertSame(2, DB::table('entrega_pedido_detalles')->count());
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 95]);
        $this->get(route('cumplimiento.show', $e['pedido']))->assertOk()->assertSee('antecedente histórico')->assertSee('El resultado vigente es Entrega parcial');
    }
}
