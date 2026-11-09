<?php

namespace Tests\Feature;

use App\Models\ConfiguracionEnvasado;
use App\Models\Envasado;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\VarianteProducto;
use App\Services\CorregirEnvasado;
use App\Services\RegistrarEnvasado;
use App\Services\VerificadorInventario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class IntegracionEnvasadoTest extends TestCase
{
    use EnvasadoEscenario,RefreshDatabase;

    public function test_tablas_nuevas_no_generan_envasados_retroactivos_y_protegen_referencias(): void
    {
        $e = $this->escenarioEnvasado(50, 75);
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame(75, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $this->assertSame('50.000', $e['insumo']->fresh()->cantidad_actual);
        foreach (['configuraciones_envasado', 'materiales_envasado', 'envasados', 'envasado_consumos', 'envasado_correcciones', 'envasado_correccion_materiales', 'envasado_reportes'] as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $fk) {
                $this->assertContains(strtolower($fk['on_delete']), ['restrict', 'no action']);
            }
        }
        $this->assertTrue(app(VerificadorInventario::class)->revisar()['correcto']);
    }

    public function test_unicidad_de_origen_no_depende_del_pedido_y_exige_origen_completo(): void
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario']);
        $registro = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 1], $e['usuario']);
        $movimiento = MovimientoInventario::where('origen_tipo', 'envasado')->where('origen_id', $registro->id)->firstOrFail();
        $datos = $movimiento->getAttributes();
        unset($datos['id']);
        try {
            DB::table('movimientos_inventario')->insert($datos);
            $this->fail('Debió rechazar duplicado');
        } catch (QueryException) {
            $this->assertSame(2, MovimientoInventario::count());
        }
        $datos['origen_tipo'] = null;
        $datos['origen_id'] = null;
        $this->expectException(QueryException::class);
        DB::table('movimientos_inventario')->insert($datos);
    }

    public function test_listar_o_imprimir_demanda_con_produccion_existente_no_modifica_saldos_ni_historial(): void
    {
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e);
        $this->actingAs($e['usuario']);
        $registro = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 2, 'pedido_id' => $pedido->id], $e['usuario']);
        $antes = MovimientoInventario::orderBy('id')->get()->toJson();
        $this->get(route('cumplimiento.lista-preparacion'))->assertOk()->assertSee('cantidades solicitadas')->assertSee('Imprimir');
        $this->get(route('envasado.show', $registro))->assertOk()->assertSee('Unidades solicitadas del pedido para este SKU: 5')->assertSee('Stock disponible general: 2');
        $this->assertSame($antes, MovimientoInventario::orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('envasados', 1);
    }

    public function test_alternativas_y_catalogo_ajenos_no_se_aceptan_ni_inventan_compatibilidades(): void
    {
        $e = $this->escenarioEnvasado();
        $otro = $this->escenarioEnvasado();
        $d = ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $otro['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 1];
        $this->actingAs($e['usuario'])->post(route('envasado.store'), $d)->assertSessionHasErrors('configuracion_envasado_id');
        $d['configuracion_envasado_id'] = $e['configuracion']->id;
        $d['pedido_id'] = $this->pedidoEnvasado($otro)->id;
        $this->post(route('envasado.store'), $d)->assertSessionHasErrors('pedido_id');
        $this->assertDatabaseCount('envasados', 0);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
    }

    public function test_no_se_alteran_categorias_existentes_ni_stock_por_configurar(): void
    {
        $e = $this->escenarioEnvasado();
        $categorias = DB::table('categorias')->orderBy('id')->get()->toJson();
        $this->actingAs($e['usuario'])->get(route('envasado.materiales', $e['presentacion']))->assertOk();
        $this->assertSame($categorias, DB::table('categorias')->orderBy('id')->get()->toJson());
        $this->assertSame('Envases', $e['insumo']->fresh()->categoria);
    }

    public function test_consumos_decimales_y_compensacion_conservan_milesimas_exactas(): void
    {
        $e = $this->escenarioEnvasado();
        $e['insumo']->update(['unidad_medida' => 'metro', 'categoria' => 'Otros']);
        $e['configuracion']->materiales()->update(['cantidad_por_unidad' => '0.125', 'funcion' => 'otro_fisico']);
        $this->actingAs($e['usuario']);
        $registro = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 12], $e['usuario']);
        $this->assertSame('98.500', $e['insumo']->fresh()->cantidad_actual);
        $datos = ['clasificacion' => 'error_administrativo', 'cantidad' => 1, 'revertidas_previas' => 0, 'motivo' => 'Material no utilizado según revisión física', 'devoluciones' => [$registro->consumos->first()->id => '0.125']];
        $this->post(route('envasado.correcciones.store', $registro), $datos)->assertRedirect();
        $this->assertSame('98.625', $e['insumo']->fresh()->cantidad_actual);
        $this->assertTrue(app(VerificadorInventario::class)->revisar()['correcto']);
    }

    public function test_acumulado_neto_por_pedido_y_presentacion_descuenta_correcciones_y_excluye_otros_origenes(): void
    {
        $e = $this->escenarioEnvasado();
        $otro = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e);
        $otroPedido = $this->pedidoEnvasado($e);
        $this->actingAs($e['usuario']);
        $registrar = function (array $escenario, int $cantidad, ?int $pedidoId) use ($e): Envasado {
            return app(RegistrarEnvasado::class)->ejecutar([
                'presentacion_producto_id' => $escenario['presentacion']->id,
                'configuracion_envasado_id' => $escenario['configuracion']->id,
                'version_configuracion' => 0, 'cantidad' => $cantidad, 'pedido_id' => $pedidoId,
            ], $e['usuario']);
        };
        $primero = $registrar($e, 2, $pedido->id);
        $segundo = $registrar($e, 3, $pedido->id);
        $registrar($e, 4, $pedido->id);
        $anticipado = $registrar($e, 5, null);
        $registrar($e, 7, $otroPedido->id);
        $pedido->detalles()->create([
            'presentacion_producto_id' => $otro['presentacion']->id, 'codigo_sku' => $otro['presentacion']->codigo_sku,
            'descripcion_producto' => 'Otra presentación de prueba', 'modalidad' => 'unidad', 'cantidad' => 6,
            'precio_unitario' => 2, 'importe' => 12,
        ]);
        $registrar($otro, 6, $pedido->id);
        foreach ([[$primero, 1], [$segundo, 3]] as [$registro, $cantidad]) {
            app(CorregirEnvasado::class)->ejecutar($registro, [
                'clasificacion' => 'error_administrativo', 'cantidad' => $cantidad, 'revertidas_previas' => 0,
                'motivo' => 'Corrección de cantidades digitadas en la prueba',
                'devoluciones' => [$registro->consumos()->firstOrFail()->id => $cantidad],
            ], $e['usuario']);
        }
        $antes = MovimientoInventario::orderBy('id')->get()->toJson();
        $this->get(route('envasado.show', $primero))->assertOk()
            ->assertViewHas('netoAcumulado', 5)->assertViewHas('stock', 17)
            ->assertSee('Total neto acumulado de envasados vinculados a este pedido y presentación: 5 unidades.');
        $this->get(route('envasado.show', $anticipado))->assertOk()
            ->assertViewHas('netoAcumulado', null)
            ->assertDontSee('Total neto acumulado de envasados vinculados');
        $this->assertSame($antes, MovimientoInventario::orderBy('id')->get()->toJson());
        $this->assertSame('confirmado', $pedido->fresh()->estado);
    }

    public function test_formulario_excluye_variantes_inactivas_y_sus_configuraciones_sin_ocultar_presentaciones_validas(): void
    {
        $e = $this->escenarioEnvasado();
        $variantes = [];
        foreach ([true, false] as $activa) {
            $variante = VarianteProducto::create([
                'producto_id' => $e['presentacion']->producto_id, 'tipo' => 'Aroma',
                'valor' => $activa ? 'Variante activa de prueba' : 'Variante inactiva de prueba', 'estado' => $activa,
            ]);
            $presentacion = $e['presentacion']->replicate();
            $presentacion->fill(['codigo_sku' => $activa ? 'SKU-VARIANTE-ACTIVA' : 'SKU-VARIANTE-INACTIVA', 'variante_producto_id' => $variante->id])->save();
            $configuracion = ConfiguracionEnvasado::factory()->create([
                'presentacion_producto_id' => $presentacion->id,
                'nombre' => $activa ? 'Alternativa de variante activa' : 'Alternativa de variante inactiva',
            ]);
            $variantes[$activa ? 'activa' : 'inactiva'] = compact('presentacion', 'configuracion');
        }
        $respuesta = $this->actingAs($e['usuario'])->get(route('envasado.create'))->assertOk()
            ->assertSee($e['presentacion']->codigo_sku)->assertSee('SKU-VARIANTE-ACTIVA')
            ->assertSee('Alternativa de variante activa')->assertDontSee('SKU-VARIANTE-INACTIVA')
            ->assertDontSee('Alternativa de variante inactiva');
        $this->assertEqualsCanonicalizing([$e['presentacion']->id, $variantes['activa']['presentacion']->id], $respuesta->viewData('presentaciones')->modelKeys());
        $this->assertEqualsCanonicalizing([$e['configuracion']->id, $variantes['activa']['configuracion']->id], $respuesta->viewData('configuraciones')->modelKeys());
        $this->post(route('envasado.previsualizar'), [
            'presentacion_producto_id' => $variantes['inactiva']['presentacion']->id,
            'configuracion_envasado_id' => $variantes['inactiva']['configuracion']->id,
            'version_configuracion' => 0, 'cantidad' => 1,
        ])->assertSessionHasErrors('presentacion_producto_id');
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
