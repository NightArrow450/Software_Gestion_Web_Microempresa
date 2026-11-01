<?php

namespace Tests;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Services\AtenderPedidoUnidades;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarPedidoUnidades;
use App\Services\RegistrarResultadoEntrega;
use App\Services\TokenOperacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait UsabilidadEscenario
{
    use VentaUnidadesEscenario;

    protected function pedidoUnico(array $e, int $cantidad, array $grupos = []): Pedido
    {
        $datos = $this->entradaUnidades($e);
        $datos['detalles'] = [['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => $cantidad]];
        $datos['agrupaciones'] = $grupos;

        return app(GestionarPedidoUnidades::class)->guardar($datos, $e['usuario']);
    }

    protected function comprobarBusquedaComercial(): void
    {
        $e = $this->escenarioUnidades();
        $marca = (string) Str::ulid();
        $e['presentacion']->producto->update(['nombre' => 'Quitásarro '.$marca]);
        $cliente = Cliente::create(['nombre' => 'JOSÉ NÚÑEZ '.$marca, 'documento' => 'CE'.$marca, 'telefono' => '999666123', 'estado' => true]);
        foreach (['jose nunez '.$marca, 'CE'.$marca, '999666123'] as $q) {
            $respuesta = $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'clientes', 'q' => $q]))->assertOk();
            $this->assertContains($cliente->id, array_column($respuesta->json('data'), 'id'));
            $respuesta->assertJsonMissingPath('data.0.telefono')->assertJsonMissingPath('data.0.documento');
        }
        foreach (['QUITASARRO '.$marca, $e['presentacion']->producto->codigo, $e['presentacion']->codigo_sku] as $q) {
            $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'productos', 'q' => $q]))->assertOk()->assertJsonPath('data.0.id', $e['presentacion']->producto_id);
        }
        for ($i = 0; $i < 23; $i++) {
            Cliente::create(['nombre' => 'Página '.$marca.' '.$i, 'estado' => true]);
        }
        $primera = $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'clientes', 'q' => 'pagina '.$marca]))->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 23);
        $segunda = $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'clientes', 'q' => 'pagina '.$marca, 'page' => 2]))->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame([], array_intersect(array_column($primera->json('data'), 'id'), array_column($segunda->json('data'), 'id')));
        $e['skus'][1]->update(['precio_unitario' => 0]);
        $e['skus'][2]->variante->update(['estado' => false]);
        $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'skus', 'producto_id' => $e['presentacion']->producto_id]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $e['presentacion']->id);
        $e['presentacion']->update(['estado' => false]);
        $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'productos', 'q' => 'quitasarro '.$marca]))->assertOk()->assertJsonCount(0, 'data');
    }

    protected function comprobarRetiroTrasEntregaParcial(): void
    {
        $e = $this->escenarioUnidades(32);
        config(['envasado.control_materiales_exacto' => false]);
        $pedido = $this->pedidoUnico($e, 54);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        $detalle = $pedido->detalles()->sole();
        $servicio = app(AtenderPedidoUnidades::class);
        $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 32, 'incidencia' => 'envases', 'motivo' => 'Faltan envases y solo se prepararon treinta y dos unidades.'], $e['usuario']);
        $pedido->refresh();
        $servicio->despachar($pedido, ['version_pedido' => $pedido->version, 'operacion' => (string) Str::ulid(), 'motivo' => 'Se despachan únicamente las treinta y dos unidades preparadas.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 32]]], $e['usuario']);
        $pedido->refresh();
        $control = app(ControlEntregaPedido::class);
        app(RegistrarResultadoEntrega::class)->ejecutar($pedido, ['version' => $control->resumen($pedido)['version'], 'detalles' => [['pedido_detalle_id' => $detalle->id, 'aceptadas' => 32, 'rechazadas' => 0]], 'observaciones' => 'El cliente acepta lo entregado y no desea recibir las otras unidades.'], $e['usuario']);
        $movimientos = DB::table('movimientos_inventario')->get()->toArray();
        $entregas = DB::table('entrega_pedido_detalles')->get()->toArray();
        $pedido->refresh();
        $datos = ['version' => $pedido->version, 'retirar' => [$detalle->id => 22], 'motivo' => 'El cliente confirma que ya no desea las veintidós unidades faltantes.', '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->post(route('pedidos.retirar-faltantes', $pedido), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('pedidos.retirar-faltantes', $pedido), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(32, (int) $detalle->fresh()->cantidad);
        $this->assertSame('64.00', $pedido->fresh()->total);
        $this->assertSame('entregado', $pedido->fresh()->estado);
        $versiones = DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->orderBy('numero')->get();
        $this->assertCount(2, $versiones);
        $this->assertSame(54, (int) json_decode($versiones->first()->datos, true)['lineas'][0]['cantidad']);
        $this->assertEquals($movimientos, DB::table('movimientos_inventario')->get()->toArray());
        $this->assertEquals($entregas, DB::table('entrega_pedido_detalles')->get()->toArray());
        $this->assertSame(0, $servicio->pendientes($pedido->fresh()));
        $this->assertSame(1, DB::table('auditoria')->where('entidad_id', $pedido->id)->where('accion', 'pedido_faltantes_retirados')->count());
    }
}
