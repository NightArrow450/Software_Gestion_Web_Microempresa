<?php

namespace Tests\Feature;

use App\Services\LecturaPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class LecturaPedidoSinSurtidosTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    public function test_relacion_y_lectura_fisica_no_dependen_de_tablas_de_surtidos(): void
    {
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e);
        $pedido->update(['modelo_comercial' => 'unidades']);
        $detalle = $pedido->detalles()->sole();
        $detalle->update(['modalidad' => 'unidad', 'cantidad' => 54]);
        foreach (['pedido_despacho_componentes', 'paquete_surtido_componentes', 'paquete_surtido_composiciones', 'pedido_paquetes_surtidos', 'formato_surtido_skus', 'formatos_surtidos'] as $tabla) {
            Schema::drop($tabla);
        }
        $pedido->unsetRelation('detalles');
        DB::enableQueryLog();

        $lineas = app(LecturaPedido::class)->lineasNormales($pedido);

        $this->assertSame(54, (int) $lineas->sole()->cantidad);
        $this->assertSame($detalle->id, $pedido->detalles->sole()->id);
        foreach (DB::getQueryLog() as $consulta) {
            $this->assertDoesNotMatchRegularExpression('/formatos_surtidos|formato_surtido_skus|pedido_paquetes_surtidos|paquete_surtido_composiciones|paquete_surtido_componentes|pedido_despacho_componentes/i', $consulta['query']);
        }
        DB::disableQueryLog();
    }

    public function test_proyeccion_no_modifica_cuentas_permisos_ni_cantidades_comerciales(): void
    {
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e);
        $detalle = $pedido->detalles()->sole();
        $detalle->update(['cantidad_fisica_ajustada' => 32]);
        $fotografia = [];
        foreach (['users', 'roles', 'permisos', 'rol_permiso', 'pedido_detalles', 'movimientos_inventario'] as $tabla) {
            $fotografia[$tabla] = DB::table($tabla)->get()->toArray();
        }

        $linea = app(LecturaPedido::class)->lineasNormales($pedido)->sole();

        $this->assertSame(32, (int) $linea->cantidad);
        $this->assertSame('unidad', $linea->modalidad);
        foreach ($fotografia as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
    }
}
