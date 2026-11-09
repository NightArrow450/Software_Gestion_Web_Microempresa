<?php

namespace Tests\Unit;

use App\Models\PedidoDetalle;
use App\Models\PresentacionProducto;
use PHPUnit\Framework\TestCase;

class EmpaquesHistoricosTest extends TestCase
{
    public function test_factor_historico_prevalece_aunque_el_catalogo_cambie(): void
    {
        $catalogo = new PresentacionProducto(['unidades_por_empaque' => 12]);
        $linea = new PedidoDetalle(['modalidad' => 'empaque', 'unidades_por_empaque' => 15]);
        $this->assertSame(15, $linea->unidadesPorEmpaqueEfectivas($catalogo));
        $catalogoActualizado = new PresentacionProducto(['unidades_por_empaque' => 24]);
        $this->assertSame(15, $linea->unidadesPorEmpaqueEfectivas($catalogoActualizado));
    }

    public function test_linea_antigua_usa_respaldo_y_unidad_no_adquiere_factor_de_empaque(): void
    {
        $catalogo = new PresentacionProducto(['unidades_por_empaque' => 12]);
        $linea = new PedidoDetalle(['modalidad' => 'empaque', 'unidades_por_empaque' => null]);
        $this->assertSame(12, $linea->unidadesPorEmpaqueEfectivas($catalogo));
        $linea->modalidad = 'unidad';
        $this->assertNull($linea->unidadesPorEmpaqueEfectivas($catalogo));
        $linea->modalidad = 'empaque';
        $linea->setRelation('presentacion', null);
        $this->assertNull($linea->unidadesPorEmpaqueEfectivas());
    }
}
