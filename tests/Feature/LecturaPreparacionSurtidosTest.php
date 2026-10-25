<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Services\GestionarSurtidos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\SurtidosEscenario;
use Tests\TestCase;

class LecturaPreparacionSurtidosTest extends TestCase
{
    use RefreshDatabase, SurtidosEscenario;

    public function test_precarga_preserva_paquetes_versiones_pendientes_y_skus_sin_consultas_por_pedido(): void
    {
        $e = $this->escenarioSurtido();
        $servicio = app(GestionarSurtidos::class);
        $pedidos = collect();
        for ($i = 0; $i < 12; $i++) {
            $pedido = $this->pedidoEnvasado($e);
            $detalle = $pedido->detalles()->create(['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'],
                'codigo_sku' => '', 'descripcion_producto' => 'Surtido histórico', 'modalidad' => 'empaque', 'cantidad' => 2,
                'unidades_por_empaque' => 12, 'precio_unitario' => 24, 'importe' => 48]);
            $servicio->guardar($detalle, $this->paquetesSurtidos($e), $e['usuario'], 'Primera composición para regresión.');
            if ($i % 2 === 0) {
                $paquetes = $this->paquetesSurtidos($e);
                $paquetes[1] = ['estado' => 'pendiente', 'componentes' => []];
                $servicio->guardar($detalle, $paquetes, $e['usuario'], 'Segunda versión pendiente para regresión.');
            }
            if ($i === 1) {
                $detalle->update(['cantidad' => 1]);
                $servicio->guardar($detalle, [$this->paquetesSurtidos($e)[0]], $e['usuario'], 'Retiro del segundo paquete, conservando su historial.');
            }
            $pedidos->push($pedido->fresh()->load('detalles.presentacion.producto', 'detalles.presentacion.variante'));
        }
        $esperado = $pedidos->map(fn (Pedido $p): array => [$servicio->pendientes($p), $servicio->lineasFisicas($p)->toArray()]);
        DB::enableQueryLog();
        $lectura = $servicio->precargarLectura($pedidos);
        $consultas = count(DB::getQueryLog());
        DB::flushQueryLog();
        $actual = $pedidos->map(fn (Pedido $p): array => [$servicio->pendientes($p, $lectura), $servicio->lineasFisicas($p, $lectura)->toArray()]);
        $this->assertSame($esperado->all(), $actual->all());
        $this->assertLessThanOrEqual(5, $consultas);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame(6, $actual->filter(fn (array $fila): bool => $fila[0] !== [])->count());
        $this->assertSame(264, $pedidos->sum(fn (Pedido $p): int => (int) $servicio->lineasFisicas($p, $lectura)->sum('cantidad')));
        $this->assertSame(1, DB::table('pedido_paquetes_surtidos')->where('activo', false)->count());
    }

    public function test_precarga_vacia_no_consulta_y_no_persiste_datos_obsoletos_en_el_servicio(): void
    {
        $e = $this->escenarioSurtido();
        $servicio = app(GestionarSurtidos::class);
        $pedido = $this->pedidoEnvasado($e);
        DB::enableQueryLog();
        $this->assertSame([], $servicio->precargarLectura(collect([$pedido->load('detalles')]))['paquetes']);
        DB::flushQueryLog();
        $this->assertSame([], $servicio->precargarLectura(collect())['paquetes']);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($pedido->detalles->toArray(), $servicio->lineasFisicas($pedido)->toArray());
        $detalle = $pedido->detalles()->create(['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'],
            'codigo_sku' => '', 'descripcion_producto' => 'Pendiente', 'modalidad' => 'empaque', 'cantidad' => 2,
            'unidades_por_empaque' => 12, 'precio_unitario' => 24, 'importe' => 48]);
        $pedido->load('detalles.presentacion.producto', 'detalles.presentacion.variante');
        $lecturaAnterior = $servicio->precargarLectura(collect([$pedido]));
        $this->assertCount(1, $servicio->pendientes($pedido, $lecturaAnterior));
        $servicio->guardar($detalle, $this->paquetesSurtidos($e), $e['usuario'], 'Composición definida después de la consulta anterior.');
        $this->assertSame([], $servicio->pendientes($pedido));
        $this->assertSame([], $servicio->pendientes($pedido, $servicio->precargarLectura(collect([$pedido]))));
    }
}
