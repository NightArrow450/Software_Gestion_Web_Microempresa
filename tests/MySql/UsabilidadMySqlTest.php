<?php

namespace Tests\MySql;

use Illuminate\Support\Facades\DB;
use Tests\UsabilidadEscenario;

class UsabilidadMySqlTest extends PruebaInventarioMySql
{
    use UsabilidadEscenario;

    public function test_busqueda_sin_tildes_y_paginacion_en_mysql(): void
    {
        $this->comprobarBusquedaComercial();
    }

    public function test_retiro_posterior_a_entrega_parcial_conserva_hechos_fisicos(): void
    {
        $this->comprobarRetiroTrasEntregaParcial();
    }

    public function test_retiro_concurrente_reenviado_solo_crea_una_version_comercial(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->pedidoUnico($e, 10);
        $detalle = $pedido->detalles()->sole();
        $datos = ['version' => $pedido->version, 'retirar' => [$detalle->id => 2], 'motivo' => 'El cliente solicita retirar definitivamente dos unidades faltantes.'];
        $op = $this->operacion($e['usuario'], '/pedidos/'.$pedido->id.'/retirar-faltantes', $datos);
        $res = $this->simultaneas('pedidos', $pedido->id, [$op, $op]);
        $this->assertSame([302, 302], array_column($res, 'estado'));
        $this->assertSame(8, (int) $detalle->fresh()->cantidad);
        $this->assertSame(2, DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->count());
    }

    public function test_dos_retiros_concurrentes_con_version_igual_no_se_acumulan(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->pedidoUnico($e, 10);
        $detalle = $pedido->detalles()->sole();
        $datos = ['version' => $pedido->version, 'retirar' => [$detalle->id => 2], 'motivo' => 'Decisión comercial informada de retirar dos unidades faltantes.'];
        $ops = array_map(fn () => $this->operacion($e['usuario'], '/pedidos/'.$pedido->id.'/retirar-faltantes', $datos), [1, 2]);
        $res = $this->simultaneas('pedidos', $pedido->id, $ops);
        $estados = array_column($res, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(8, (int) $detalle->fresh()->cantidad);
        $this->assertSame(2, DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->count());
    }
}
