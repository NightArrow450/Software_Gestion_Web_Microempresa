<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Services\LecturaPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\VentaUnidadesEscenario;

class LecturaPreparacionSurtidosTest extends TestCase
{
    use RefreshDatabase, VentaUnidadesEscenario;

    public function test_precarga_preserva_unidades_y_skus_sin_consultas_por_pedido(): void
    {
        $e = $this->escenarioUnidades();
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->registrarUnidades($e)->id;
        }
        $pedidos = Pedido::with(['detalles.presentacion.producto', 'detalles.presentacion.variante'])->whereIn('id', $ids)->get();
        $servicio = app(LecturaPedido::class);
        DB::enableQueryLog();

        $lectura = $servicio->precargarLectura($pedidos);
        $unidades = $pedidos->sum(fn (Pedido $pedido): int => (int) $servicio->lineasFisicas($pedido, $lectura)->sum('cantidad'));

        $this->assertSame(120, $unidades);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertDatabaseCount('pedido_agrupaciones', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('formatos_surtidos', 0);
    }

    public function test_precarga_vacia_no_consulta_y_no_persiste_datos_obsoletos_en_el_servicio(): void
    {
        $e = $this->escenarioUnidades();
        $servicio = app(LecturaPedido::class);
        DB::enableQueryLog();
        $this->assertSame(['despachadas' => []], $servicio->precargarLectura(collect()));
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $pedido = $this->registrarUnidades($e);
        $pedido->load('detalles');
        $anterior = $servicio->precargarLectura(collect([$pedido]));
        $this->assertSame(24, (int) $servicio->lineasFisicas($pedido, $anterior)->sum('cantidad'));
        $this->despacharUnidades($pedido, $e);
        $pedido->refresh()->load('detalles');
        $this->assertSame(24, (int) $servicio->lineasFisicas($pedido, $anterior)->sum('cantidad'));
        $this->assertSame(0, (int) $servicio->lineasFisicas($pedido, $servicio->precargarLectura(collect([$pedido])))->sum('cantidad'));
        $this->assertSame(0, (int) $servicio->lineasFisicas($pedido)->sum('cantidad'));
    }
}
