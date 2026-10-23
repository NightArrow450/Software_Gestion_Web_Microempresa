<?php

namespace Tests\MySql;

use App\Models\Envasado;
use App\Models\Inventario;
use App\Services\RegistrarEnvasado;
use App\Services\VerificadorInventario;
use PHPUnit\Framework\Attributes\Group;
use Tests\EnvasadoEscenario;

#[Group('mysql-concurrencia')]
class ConcurrenciaEnvasadoTest extends PruebaInventarioMySql
{
    use EnvasadoEscenario;

    private function datosEnvasado(array $e, int $cantidad = 8): array
    {
        return ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => $cantidad];
    }

    public function test_dos_envasados_compiten_por_materiales_y_solo_uno_se_confirma(): void
    {
        $e = $this->escenarioEnvasado(10);
        $ops = array_map(fn () => $this->operacion($e['usuario'], '/envasados', $this->datosEnvasado($e)), [1, 2]);
        $res = $this->simultaneas('presentaciones_producto', $e['presentacion']->id, $ops);
        $estados = array_column($res, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame('2.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(1, Envasado::where('presentacion_producto_id', $e['presentacion']->id)->count());
        $this->assertSame(8, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
    }

    public function test_reenvio_simultaneo_de_envasado_se_procesa_una_vez(): void
    {
        $e = $this->escenarioEnvasado();
        $op = $this->operacion($e['usuario'], '/envasados', $this->datosEnvasado($e));
        $res = $this->simultaneas('presentaciones_producto', $e['presentacion']->id, [$op, $op]);
        $this->assertSame([302, 302], array_column($res, 'estado'));
        $this->assertSame('92.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(1, Envasado::where('presentacion_producto_id', $e['presentacion']->id)->count());
    }

    public function test_correcciones_simultaneas_con_tokens_distintos_no_revierten_dos_veces(): void
    {
        $e = $this->escenarioEnvasado();
        $this->actingAs($e['usuario']);
        $envasado = app(RegistrarEnvasado::class)->ejecutar($this->datosEnvasado($e), $e['usuario']);
        $datos = ['clasificacion' => 'error_administrativo', 'cantidad' => 8, 'revertidas_previas' => 0, 'motivo' => 'Anulación de registro administrativo duplicado', 'devoluciones' => [$envasado->consumos->first()->id => 8]];
        $ops = array_map(fn () => $this->operacion($e['usuario'], '/envasados/'.$envasado->id.'/correcciones', $datos), [1, 2]);
        $res = $this->simultaneas('envasados', $envasado->id, $ops);
        $estados = array_column($res, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(1, $envasado->correcciones()->count());
    }

    public function test_envasado_y_despacho_simultaneos_conservan_cadena_y_no_duplican_consumos(): void
    {
        $e = $this->escenarioEnvasado(100, 5);
        $pedido = $this->pedidoEnvasado($e, 'listo_despacho');
        $res = $this->simultaneas('presentaciones_producto', $e['presentacion']->id, [$this->operacion($e['usuario'], '/envasados', $this->datosEnvasado($e)), $this->operacion($e['usuario'], '/cumplimiento/'.$pedido->id.'/despachar', [])]);
        $this->assertSame([302, 302], array_column($res, 'estado'));
        $this->assertSame('92.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame(8, (int) Inventario::where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $reporte = app(VerificadorInventario::class)->revisar();
        $inconsistenciasDelEscenario = array_values(array_filter($reporte['inconsistencias'], fn (array $fila): bool => ($fila['tipo'] === 'producto' && $fila['id'] === $e['presentacion']->id) ||
            ($fila['tipo'] === 'insumo' && $fila['id'] === $e['insumo']->id)
        ));
        $this->assertSame([], $inconsistenciasDelEscenario);
        $this->assertSame([], $reporte['huerfanos']);
    }
}
