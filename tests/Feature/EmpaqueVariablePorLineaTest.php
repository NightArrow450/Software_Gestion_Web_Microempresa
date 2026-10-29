<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Services\CalculadoraPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmpaqueVariablePorLineaTest extends TestCase
{
    use RefreshDatabase;

    /** Prepara una fotografía anterior al rediseño y prueba su ajuste por la ruta real. */
    private function ajustarHistorico(array $datos): TestResponse
    {
        $pedido = Pedido::create(['cliente_id' => $datos['cliente_id'], 'usuario_id' => auth()->id(), 'cliente_nombre' => 'Cliente Prueba',
            'fecha_pedido' => $datos['fecha_pedido'], 'fecha_entrega' => $datos['fecha_entrega'], 'condicion_pago' => 'contado', 'estado' => 'registrado', 'total' => 0]);
        $total = 0;
        foreach ($datos['detalles'] as $i => $fila) {
            $sku = PresentacionProducto::findOrFail($fila['presentacion_producto_id']);
            $factor = $fila['modalidad'] === 'empaque' ? (int) ($fila['unidades_por_empaque'] ?? $sku->unidades_por_empaque) : null;
            $precio = $factor === null ? $sku->precio_unitario : app(CalculadoraPedido::class)->calcularImporte($factor, $sku->precio_unitario);
            $importe = app(CalculadoraPedido::class)->calcularImporte($fila['cantidad'], $precio);
            $detalle = $pedido->detalles()->create(['presentacion_producto_id' => $sku->id, 'codigo_sku' => $sku->codigo_sku, 'descripcion_producto' => $sku->producto->nombre,
                'modalidad' => $fila['modalidad'], 'unidades_por_empaque' => $factor, 'cantidad' => $fila['cantidad'], 'precio_unitario' => $precio, 'importe' => $importe]);
            $datos['detalles'][$i]['id'] = $detalle->id;
            $total += (int) round((float) $importe * 100);
        }
        $pedido->update(['total' => number_format($total / 100, 2, '.', '')]);
        $pedido->historialEstados()->create(['usuario_id' => auth()->id(), 'estado_nuevo' => 'registrado', 'motivo' => 'Fotografía histórica ficticia anterior al rediseño.']);
        $datos['motivo'] = 'Ajuste auditado que debe conservar tamaños y tarifas originales.';

        return $this->put(route('pedidos.update', $pedido), $datos);
    }

    private function escenario(?array $permisos = null): array
    {
        $this->freezeTime();
        $rol = Role::create(['nombre' => 'Operaciones Comerciales']);
        foreach ($permisos ?? ['pedidos.crear', 'pedidos.editar', 'pedidos.ver', 'inventario.ver', 'cumplimiento.despachar'] as $nombre) {
            $rol->permisos()->attach(Permiso::firstOrCreate(['nombre' => $nombre])->id);
        }
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'empaques@example.com', 'password' => 'Prueba12345', 'role_id' => $rol->id, 'status' => true]);
        $this->actingAs($usuario);
        $cliente = Cliente::create(['nombre' => 'Cliente Prueba', 'estado' => true]);
        $categoria = Categoria::create(['nombre' => 'Limpieza', 'estado' => true]);
        $producto = Producto::create(['codigo' => 'PRO-1', 'nombre' => 'Limpiador', 'categoria_id' => $categoria->id, 'estado' => true]);
        $presentacion = PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => 'PRO-1-L', 'contenido' => 1, 'unidad_medida' => 'L', 'tipo_empaque' => 'Caja', 'unidades_por_empaque' => 12, 'venta_por_unidad' => true, 'venta_por_empaque' => true, 'precio_unitario' => '2.50', 'estado' => true]);
        $datos = ['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $presentacion->id, 'modalidad' => 'empaque', 'cantidad' => 5, 'unidades_por_empaque' => 15]]];

        return [$datos, $presentacion, $usuario];
    }

    public function test_migracion_copia_tamano_sin_alterar_los_datos_existentes(): void
    {
        [$datos, $presentacion, $usuario] = $this->escenario();
        $pedido = Pedido::create(['cliente_id' => $datos['cliente_id'], 'usuario_id' => $usuario->id, 'fecha_pedido' => today(), 'fecha_entrega' => today(), 'condicion_pago' => 'contado', 'estado' => 'registrado', 'total' => '199.99', 'cliente_nombre' => 'Cliente Prueba']);
        $base = ['codigo_sku' => $presentacion->codigo_sku, 'descripcion_producto' => 'Descripción histórica', 'cantidad' => 5, 'precio_unitario' => '39.99', 'importe' => '199.95'];
        foreach (['empaque', 'unidad'] as $modalidad) {
            $pedido->detalles()->create($base + ['presentacion_producto_id' => $presentacion->id, 'modalidad' => $modalidad]);
        }
        $pedido->detalles()->create($base + ['modalidad' => 'empaque']);
        $migracion = require database_path('migrations/2026_10_22_023227_add_unidades_por_empaque_to_pedido_detalles_table.php');
        $migracion->down();
        $antes = DB::table('pedido_detalles')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
        $migracion->up();
        $despues = DB::table('pedido_detalles')->orderBy('id')->get();

        $this->assertSame([12, null, null], $despues->pluck('unidades_por_empaque')->all());
        $this->assertSame($antes, $despues->map(function ($fila): array {
            $datos = (array) $fila;
            unset($datos['unidades_por_empaque']);

            return $datos;
        })->all());
        $this->assertSame('199.99', $pedido->fresh()->total);
    }

    public function test_cinco_cajas_historicas_de_quince_conservan_precio_e_ignoran_importe_manipulado(): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][0]['precio_unitario'] = '0.01';
        $datos['detalles'][0]['importe'] = '0.01';
        $datos['total'] = '0.01';

        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 15, 'precio_unitario' => '37.50', 'cantidad' => 5, 'importe' => '187.50']);
        $this->assertDatabaseHas('pedidos', ['total' => '187.50']);
    }

    public function test_omitir_tamano_conserva_el_historico_y_un_cambio_exige_ajuste_explicito(): void
    {
        [$datos] = $this->escenario();
        unset($datos['detalles'][0]['unidades_por_empaque']);
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 12, 'precio_unitario' => '30.00']);

        $datos['detalles'][0]['unidades_por_empaque'] = 15;
        $datos['motivo'] = 'Se revisa explícitamente la distribución física histórica.';
        $datos['detalles'][0]['id'] = $pedido->detalles()->sole()->id;
        $this->put(route('pedidos.update', $pedido), $datos)->assertSessionHasErrors('detalles');
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 12, 'precio_unitario' => '30.00']);
        $this->assertSame('150.00', $pedido->fresh()->total);
    }

    public static function tamanosInvalidos(): array
    {
        return ['uno' => [1], 'mil uno' => [1001], 'decimal' => ['2.5'], 'texto' => ['doce'], 'arreglo' => [[15]]];
    }

    #[DataProvider('tamanosInvalidos')]
    public function test_tamano_invalido_rechaza_alta_y_edicion_sin_cambiar_datos(mixed $tamano): void
    {
        [$datos] = $this->escenario();
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $datos['detalles'][0]['unidades_por_empaque'] = $tamano;

        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors('detalles.0.modalidad');
        $datos['motivo'] = 'Intento de introducir un tamaño histórico inválido.';
        $this->put(route('pedidos.update', $pedido), $datos)->assertSessionHasErrors('detalles.0.unidades_por_empaque');
        $this->assertDatabaseCount('pedidos', 1);
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 15, 'importe' => '187.50']);
    }

    public static function tamanosValidos(): array
    {
        return ['dos' => [2, '5.00'], 'mil' => [1000, '2500.00']];
    }

    #[DataProvider('tamanosValidos')]
    public function test_acepta_tamanos_historicos_limite_sin_convertirlos(int $tamano, string $precio): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][0]['unidades_por_empaque'] = $tamano;
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => $tamano, 'precio_unitario' => $precio]);
    }

    public function test_unidad_ignora_el_tamano_incluso_si_es_invalido(): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][0]['modalidad'] = 'unidad';
        $datos['detalles'][0]['unidades_por_empaque'] = ['manipulado'];
        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => null, 'precio_unitario' => '2.50', 'importe' => '12.50']);
    }

    public function test_sin_precio_base_rechaza_empaque_con_mensaje_claro(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $presentacion->update(['precio_unitario' => null, 'precio_empaque' => '30.00']);
        $datos['motivo'] = 'La tarifa histórica permanece válida sin precio nuevo.';
        $this->put(route('pedidos.update', $pedido), $datos)->assertSessionHasNoErrors();
        $datos['detalles'][0]['modalidad'] = 'unidad';
        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors('detalles.0.presentacion_producto_id');
        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_importe_de_linea_respeta_tope_inclusivo(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $presentacion->update(['precio_unitario' => '333333.33']);
        $datos['detalles'][0]['unidades_por_empaque'] = 3;
        $datos['detalles'][0]['cantidad'] = 1;
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['importe' => '999999.99']);

        $datos['detalles'][0]['cantidad'] = 2;
        $datos['motivo'] = 'Intento de superar el importe máximo de una línea histórica.';
        $this->put(route('pedidos.update', Pedido::firstOrFail()), $datos)->assertSessionHasErrors('detalles.0.cantidad');
        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_cajas_de_doce_y_quince_son_lineas_distintas_del_mismo_pedido(): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][] = array_replace($datos['detalles'][0], ['unidades_por_empaque' => 12]);
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pedido_detalles', 2);
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 12, 'importe' => '150.00']);
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 15, 'importe' => '187.50']);
        $this->assertSame('337.50', Pedido::firstOrFail()->total);
    }

    public function test_dos_cajas_de_quince_repetidas_se_rechazan_en_servidor(): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][] = $datos['detalles'][0];
        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors('detalles.1.presentacion_producto_id');
        $this->assertDatabaseCount('pedidos', 0);
        $this->assertDatabaseCount('pedido_detalles', 0);
    }

    public function test_tamano_omitido_y_explicito_del_catalogo_son_duplicados(): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][0]['unidades_por_empaque'] = 12;
        $datos['detalles'][] = $datos['detalles'][0];
        unset($datos['detalles'][1]['unidades_por_empaque']);
        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors('detalles.1.presentacion_producto_id');
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_formulario_incluye_tamano_editable_y_catalogo_con_factor(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('pedidos.edit', Pedido::firstOrFail()))->assertOk()
            ->assertSee('js-tamano-empaque')
            ->assertSee('min="2" max="1000" step="1"', false)
            ->assertViewHas('presentaciones', fn (array $filas): bool => $filas[0]['unidades_por_empaque'] === 12 && $filas[0]['tipo_empaque'] === 'Caja');
        $presentacion->update(['unidades_por_empaque' => 20]);
        $this->get(route('pedidos.edit', Pedido::firstOrFail()))->assertOk()
            ->assertSee('js-tamano-empaque')
            ->assertViewHas('pedido', fn (Pedido $pedido): bool => $pedido->detalles->first()->unidades_por_empaque === 15);
    }

    public function test_cinco_cajas_de_quince_conservan_precio_lista_disponibilidad_y_despacho_al_cambiar_catalogo(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $pedido->update(['estado' => 'confirmado']);
        $presentacion->update(['unidades_por_empaque' => 20, 'precio_unitario' => '3.00']);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 100]);

        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertSee('5 empaques de 15')
            ->assertViewHas('filas', fn ($filas): bool => $filas->count() === 1 && $filas[0]['total_unidades'] === 75 && $filas[0]['desglose']['empaque|15']['cantidad'] === 5);
        $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertOk()
            ->assertViewHas('revision', fn ($revision): bool => $revision[0]['necesario'] === 75 && $revision[0]['alcanza'] === true);
        $this->get(route('pedidos.show', $pedido))->assertOk()->assertSee('Empaque de 15')->assertDontSee('Empaque de 20');
        $this->get(route('cumplimiento.show', $pedido))->assertOk()->assertSee('Empaque de 15');
        $this->get(route('pedidos.imprimir-por-fecha', ['fecha_pedido' => today()->toDateString()]))->assertOk()->assertSee('Empaque de 15');
        $this->assertDatabaseHas('pedido_detalles', ['pedido_id' => $pedido->id, 'unidades_por_empaque' => 15, 'precio_unitario' => '37.50', 'importe' => '187.50']);
        $this->assertSame('187.50', $pedido->fresh()->total);

        $pedido->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 25]);
        $this->assertDatabaseHas('movimientos_inventario', ['pedido_id' => $pedido->id, 'cantidad' => 75, 'stock_anterior' => 100, 'stock_nuevo' => 25, 'insumo_id' => null]);
        $this->assertSame('despachado', $pedido->fresh()->estado);
    }

    public function test_doce_y_quince_consolidan_la_lista_y_suman_un_solo_descuento_por_presentacion(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $datos['detalles'][] = array_replace($datos['detalles'][0], ['unidades_por_empaque' => 12]);
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $pedido->update(['estado' => 'confirmado']);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 200]);

        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertSee('5 empaques de 12')->assertSee('5 empaques de 15')
            ->assertViewHas('filas', fn ($filas): bool => $filas->count() === 1 && $filas[0]['total_unidades'] === 135 && collect($filas[0]['desglose'])->pluck('total_unidades')->sort()->values()->all() === [60, 75]);
        $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertOk()
            ->assertViewHas('revision', fn ($revision): bool => $revision->count() === 1 && $revision[0]['necesario'] === 135);

        $pedido->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 65]);
        $this->assertDatabaseHas('movimientos_inventario', ['pedido_id' => $pedido->id, 'cantidad' => 135]);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_linea_antigua_null_respalda_con_catalogo_en_todos_los_consumidores(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $pedido->detalles()->update(['unidades_por_empaque' => null]);
        $pedido->update(['estado' => 'confirmado']);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 100]);

        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertSee('5 empaques de 12')
            ->assertViewHas('filas', fn ($filas): bool => $filas[0]['total_unidades'] === 60);
        $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertOk()
            ->assertViewHas('revision', fn ($revision): bool => $revision[0]['necesario'] === 60);
        $this->get(route('pedidos.show', $pedido))->assertOk()->assertSee('Empaque de 12');
        $this->get(route('pedidos.imprimir-por-fecha', ['fecha_pedido' => today()->toDateString()]))->assertOk()->assertSee('Empaque de 12');
        $pedido->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('movimientos_inventario', ['pedido_id' => $pedido->id, 'cantidad' => 60]);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 40]);
    }

    public function test_lista_conserva_unidades_historicas_aun_sin_presentacion(): void
    {
        [$datos] = $this->escenario();
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $pedido->update(['estado' => 'confirmado']);
        $pedido->detalles()->update(['presentacion_producto_id' => null]);
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertSee('Presentación histórica')->assertSee('5 empaques de 15')
            ->assertViewHas('filas', fn ($filas): bool => $filas[0]['total_unidades'] === 75);
    }

    public function test_stock_insuficiente_en_otro_sku_revierte_todo_el_despacho(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $otra = $presentacion->replicate();
        $otra->codigo_sku = 'PRO-2-L';
        $otra->save();
        $datos['detalles'][] = ['presentacion_producto_id' => $otra->id, 'modalidad' => 'unidad', 'cantidad' => 2];
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $pedido->update(['estado' => 'listo_despacho']);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 100]);
        Inventario::create(['presentacion_producto_id' => $otra->id, 'cantidad_actual' => 1]);

        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasErrors('inventario');
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 100]);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $otra->id, 'cantidad_actual' => 1]);
        $this->assertSame('listo_despacho', $pedido->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('pedido_estados_historial', 1);
        $this->assertNull($pedido->fresh()->despachado_en);
    }

    public function test_no_descuenta_dos_veces_ni_descuenta_insumos(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $insumo = Insumo::create(['codigo' => 'ENV-1L', 'nombre' => 'Botella vacía', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => '100.000']);
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $pedido->update(['estado' => 'listo_despacho']);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 100]);

        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 25]);
        $this->assertDatabaseHas('insumos', ['id' => $insumo->id, 'cantidad_actual' => '100.000']);
        $this->assertDatabaseHas('movimientos_inventario', ['pedido_id' => $pedido->id, 'cantidad' => 75, 'insumo_id' => null]);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseCount('pedido_estados_historial', 2);
    }

    public function test_modalidad_unidad_descuenta_solo_la_cantidad_sin_multiplicar(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $datos['detalles'][0]['modalidad'] = 'unidad';
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $pedido->update(['estado' => 'confirmado']);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 100]);
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()
            ->assertViewHas('filas', fn ($filas): bool => $filas[0]['total_unidades'] === 5 && $filas[0]['desglose']['unidad|0']['por_empaque'] === null);

        $pedido->update(['estado' => 'listo_despacho']);
        $this->post(route('cumplimiento.despachar', $pedido))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['pedido_id' => $pedido->id, 'unidades_por_empaque' => null]);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 95]);
        $this->assertDatabaseHas('movimientos_inventario', ['pedido_id' => $pedido->id, 'cantidad' => 5]);
    }

    public function test_permisos_existentes_siguen_exigiendose_con_empaque_variable(): void
    {
        [$datos, $presentacion, $usuario] = $this->escenario([]);
        $this->post(route('pedidos.store'), $datos)->assertForbidden();
        $this->assertDatabaseCount('pedidos', 0);
        $usuario->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => 'pedidos.crear'])->id);
        $usuario->unsetRelation('role');
        $this->ajustarHistorico($datos)->assertForbidden();
        $pedido = Pedido::firstOrFail();
        $usuario->role->permisos()->detach();
        $usuario->unsetRelation('role');

        $this->get(route('pedidos.edit', $pedido))->assertForbidden();
        $this->put(route('pedidos.update', $pedido), $datos)->assertForbidden();
        $this->get(route('cumplimiento.lista-preparacion'))->assertForbidden();
        $this->get(route('inventario.index', ['pedido_id' => $pedido->id]))->assertForbidden();
        $pedido->update(['estado' => 'listo_despacho']);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 100]);
        $this->post(route('cumplimiento.despachar', $pedido))->assertForbidden();
        $this->assertSame('listo_despacho', $pedido->fresh()->estado);
        $this->assertDatabaseHas('inventarios', ['cantidad_actual' => 100]);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_consultar_lista_para_pantalla_o_impresion_no_modifica_datos(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $datos['detalles'][] = array_replace($datos['detalles'][0], ['unidades_por_empaque' => 12]);
        $this->ajustarHistorico($datos)->assertRedirect()->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $pedido->update(['estado' => 'confirmado']);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 200]);
        Insumo::create(['codigo' => 'ENV-1L', 'nombre' => 'Botella vacía', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => '100.000']);
        $antes = [];
        foreach (['pedidos', 'pedido_detalles', 'pedido_estados_historial', 'inventarios', 'insumos', 'movimientos_inventario'] as $tabla) {
            $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->map(fn ($registro): array => (array) $registro)->all();
        }

        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertSee('Imprimir')->assertSee('window.print()', false);
        $this->get(route('cumplimiento.lista-preparacion', ['fecha_desde' => today()->toDateString(), 'fecha_hasta' => today()->toDateString()]))->assertOk();

        foreach ($antes as $tabla => $registros) {
            $this->assertSame($registros, DB::table($tabla)->orderBy('id')->get()->map(fn ($registro): array => (array) $registro)->all(), $tabla);
        }
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 15, 'precio_unitario' => '37.50', 'importe' => '187.50']);
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 12, 'precio_unitario' => '30.00', 'importe' => '150.00']);
        $this->assertSame('337.50', $pedido->fresh()->total);
    }
}
