<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Services\ConfigurarFormatoSurtido;
use App\Services\GestionarSurtidos;
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

    private function registrar(array $e, ?array $paquetes = null): Pedido
    {
        $pedidoBase = $this->pedidoEnvasado($e, 'registrado');
        $cliente = $pedidoBase->cliente_id;
        $pedidoBase->detalles()->delete();
        $pedidoBase->delete();
        $this->post(route('pedidos.store'), ['cliente_id' => $cliente, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado', 'detalles' => [['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'], 'modalidad' => 'empaque', 'cantidad' => 2, 'unidades_por_empaque' => 12, 'precio_unitario' => '0.01', 'paquetes_json' => json_encode($paquetes ?? $this->paquetesSurtidos($e))]]])->assertRedirect()->assertSessionHasNoErrors();

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
}
