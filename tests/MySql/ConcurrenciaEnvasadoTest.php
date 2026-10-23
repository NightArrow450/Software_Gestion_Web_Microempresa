<?php

namespace Tests\MySql;

use App\Models\Auditoria;
use App\Models\Envasado;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Services\RegistrarEnvasado;
use App\Services\VerificadorInventario;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_lectura_actual_de_materiales_coincide_con_version_bloqueada(): void
    {
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $e = $this->escenarioEnvasado();
        $nuevo = Insumo::create(['codigo' => 'REV'.Str::ulid(), 'nombre' => 'Material alternativo de revisión', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 100, 'estado' => true]);
        config(['database.connections.revision_configuracion' => config('database.connections.mysql_concurrencia')]);
        $otra = DB::connection('revision_configuracion');
        $this->assertSame(DB::connection()->getDatabaseName(), $otra->getDatabaseName());
        $intercalado = false;
        DB::listen(function (QueryExecuted $consulta) use (&$intercalado, $otra, $e, $nuevo): void {
            if ($intercalado || $consulta->connectionName !== 'mysql_concurrencia' || ! str_contains($consulta->sql, 'count(*)') || ! str_contains($consulta->sql, '`presentaciones_producto`')) {
                return;
            }
            $intercalado = true;
            $otra->transaction(function () use ($otra, $e, $nuevo): void {
                $otra->table('configuraciones_envasado')->where('id', $e['configuracion']->id)->update(['version' => 1]);
                $otra->table('materiales_envasado')->where('configuracion_envasado_id', $e['configuracion']->id)->update(['estado' => false]);
                $otra->table('materiales_envasado')->insert(['configuracion_envasado_id' => $e['configuracion']->id, 'insumo_id' => $nuevo->id, 'funcion' => 'envase', 'base_consumo' => 'unidad', 'unidades_por_empaque' => 0, 'cantidad_por_unidad' => 1, 'estado' => true, 'created_at' => now(), 'updated_at' => now()]);
            });
        });
        $this->actingAs($e['usuario']);
        $registro = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 1, 'cantidad' => 1], $e['usuario']);
        $this->assertTrue($intercalado);
        $this->assertSame($nuevo->id, $registro->consumos()->firstOrFail()->insumo_id, 'Los materiales deben corresponder a la versión validada bajo REPEATABLE READ.');
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertSame('99.000', $nuevo->fresh()->cantidad_actual);
    }

    public function test_ediciones_simultaneas_de_configuracion_rechazan_version_obsoleta_y_auditan_solo_un_cambio(): void
    {
        $e = $this->escenarioEnvasado();
        $operaciones = [];
        foreach ([2, 3] as $consumo) {
            $datos = ['nombre' => 'Alternativa revisada', 'estado' => 1, 'version' => 0,
                'materiales' => [['insumo_id' => $e['insumo']->id, 'funcion' => 'envase', 'base_consumo' => 'unidad', 'cantidad_por_unidad' => $consumo]]];
            $operaciones[] = $this->operacion($e['usuario'], '/presentaciones/'.$e['presentacion']->id.'/materiales-envasado/'.$e['configuracion']->id, $datos) + ['metodo' => 'PUT'];
        }
        $resultados = $this->simultaneas('presentaciones_producto', $e['presentacion']->id, $operaciones);
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(1, $e['configuracion']->fresh()->version);
        $this->assertContains($e['configuracion']->materiales()->firstOrFail()->cantidad_por_unidad, ['2.000', '3.000']);
        $this->assertSame(1, Auditoria::where('entidad', 'configuracion_envasado')->where('entidad_id', $e['configuracion']->id)->where('accion', 'configuracion_envasado_modificada')->where('usuario_id', $e['usuario']->id)->count());
        $this->assertSame('100.000', $e['insumo']->fresh()->cantidad_actual);
    }
}
