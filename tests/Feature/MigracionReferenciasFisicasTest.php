<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class MigracionReferenciasFisicasTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    public function test_copia_hechos_fisicos_sin_conversiones_ni_cambios_de_cuentas_o_movimientos(): void
    {
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e);
        $formato = DB::table('formatos_surtidos')->insertGetId(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'Fotografía anterior ficticia',
            'tipo_envase' => 'Botella', 'contenido' => 1, 'unidad_medida' => 'L', 'unidades_por_empaque' => 12, 'precio_empaque' => 24, 'estado' => true]);
        $detalle = $pedido->detalles()->create(['tipo_linea' => 'surtido', 'codigo_sku' => '', 'descripcion_producto' => 'Línea anterior',
            'modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'cantidad' => 1, 'precio_unitario' => 24, 'importe' => 24]);
        DB::table('pedido_detalles')->where('id', $detalle->id)->update(['formato_surtido_id' => $formato]);
        $paquete = DB::table('pedido_paquetes_surtidos')->insertGetId(['pedido_detalle_id' => $detalle->id, 'numero' => 1, 'version' => 1, 'activo' => true]);
        $revision = DB::table('paquete_surtido_composiciones')->insertGetId(['paquete_surtido_id' => $paquete, 'version' => 1, 'estado' => 'definida',
            'usuario_id' => $e['usuario']->id, 'motivo' => 'Dato histórico ficticio para ensayar la migración.', 'created_at' => now()]);
        $campos = ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 12, 'sku' => $e['presentacion']->codigo_sku, 'descripcion' => 'Descripción conservada'];
        $componente = DB::table('paquete_surtido_componentes')->insertGetId($campos + ['composicion_id' => $revision]);
        DB::table('pedido_despacho_componentes')->insert($campos + ['pedido_detalle_id' => $detalle->id, 'paquete_surtido_id' => $paquete,
            'componente_id' => $componente, 'importe_centimos' => 2400, 'numero_paquete' => 1, 'created_at' => now()]);
        $antes = [];
        foreach (Schema::getTableListing(null, false) as $tabla) {
            if ($tabla !== 'pedido_referencias_fisicas') {
                $antes[$tabla] = DB::table($tabla)->get()->toArray();
            }
        }
        Schema::drop('pedido_referencias_fisicas');
        $migracion = require database_path('migrations/2026_10_30_002816_create_pedido_referencias_fisicas_table.php');

        $migracion->up();

        $columnas = ['id', 'pedido_detalle_id', 'presentacion_producto_id', 'cantidad', 'importe_centimos', 'sku', 'descripcion', 'numero_paquete', 'created_at'];
        $this->assertEquals(DB::table('pedido_despacho_componentes')->select($columnas)->get()->toArray(), DB::table('pedido_referencias_fisicas')->get()->toArray());
        foreach ($antes as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
        $this->assertDatabaseCount('movimientos_inventario', 0);
        try {
            DB::table('pedido_referencias_fisicas')->update(['cantidad' => 999]);
            $this->fail('La copia física debe conservar su inmutabilidad.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('inmutable', $error->getMessage());
        }
        $this->assertSame(12, (int) DB::table('pedido_referencias_fisicas')->sole()->cantidad);
    }
}
