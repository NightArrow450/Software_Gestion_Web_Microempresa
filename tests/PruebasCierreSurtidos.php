<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/** Casos compartidos por SQLite en memoria y MySQL temporal de CI. */
trait PruebasCierreSurtidos
{
    use EnvasadoEscenario;

    public static function escenariosCierre(): array
    {
        return array_combine(['nueva', 'saneada', 'historica', 'parcial', 'vista', 'fk', 'trigger', 'referencia', 'fotografia'],
            array_map(fn (string $caso): array => [$caso], ['nueva', 'saneada', 'historica', 'parcial', 'vista', 'fk', 'trigger', 'referencia', 'fotografia']));
    }

    #[DataProvider('escenariosCierre')]
    public function test_cierre_ordinario_seguro_sin_perder_historia(string $caso): void
    {
        $this->prepararBaseCierre($caso);
        $this->assertSame([], Schema::getTableListing(null, false));
        Log::spy();
        $opciones = ['--force' => true, '--no-interaction' => true];
        if ($caso === 'nueva') {
            $this->assertSame(0, Artisan::call('migrate', $opciones));
            $this->assertCount(46, Schema::getTableListing(null, false));
            $this->assertDatabaseCount('migrations', 50);
            $antes = $this->fotografiaCierre();
            $this->assertSame(0, Artisan::call('migrate', $opciones));
            $this->assertSame($antes, $this->fotografiaCierre());
            $this->assertFalse(Schema::hasTable('formatos_surtidos'));
            $this->assertFalse(Schema::hasColumn('pedido_detalles', 'formato_surtido_id'));
            Log::shouldHaveReceived('info')->once()->withArgs(fn (string $mensaje, array $contexto): bool => $contexto['estado_esquema'] === 'estructuras_vacias_retiradas');

            return;
        }
        $this->assertSame(0, Artisan::call('migrate', $opciones + ['--path' => EsquemaSurtidosHistorico::rutas(), '--realpath' => true]));
        $e = $this->escenarioEnvasado();
        $pedido = $this->pedidoEnvasado($e);
        if (in_array($caso, ['historica', 'fotografia'], true)) {
            $formato = DB::table('formatos_surtidos')->insertGetId(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'Historia ficticia conservada', 'tipo_envase' => 'Botella',
                'contenido' => 1, 'unidad_medida' => 'L', 'unidades_por_empaque' => 12, 'precio_empaque' => 24, 'estado' => true]);
            DB::table('formato_surtido_skus')->insert(['formato_surtido_id' => $formato, 'presentacion_producto_id' => $e['presentacion']->id]);
            $detalle = $pedido->detalles()->create(['tipo_linea' => 'surtido', 'codigo_sku' => '', 'descripcion_producto' => 'Historia anterior', 'modalidad' => 'empaque',
                'unidades_por_empaque' => 12, 'cantidad' => 1, 'precio_unitario' => 24, 'importe' => 24]);
            DB::table('pedido_detalles')->where('id', $detalle->id)->update(['formato_surtido_id' => $formato]);
            $paquete = DB::table('pedido_paquetes_surtidos')->insertGetId(['pedido_detalle_id' => $detalle->id, 'numero' => 1, 'version' => 1, 'activo' => true]);
            $revision = DB::table('paquete_surtido_composiciones')->insertGetId(['paquete_surtido_id' => $paquete, 'version' => 1, 'estado' => 'definida', 'usuario_id' => $e['usuario']->id,
                'motivo' => 'Historia ficticia para validar conservación.', 'created_at' => now()]);
            $campos = ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 12, 'sku' => $e['presentacion']->codigo_sku, 'descripcion' => 'Fotografía conservada'];
            $componente = DB::table('paquete_surtido_componentes')->insertGetId($campos + ['composicion_id' => $revision]);
            $fisica = $campos + ['pedido_detalle_id' => $detalle->id, 'importe_centimos' => 2400, 'numero_paquete' => 1, 'created_at' => now()->format('Y-m-d H:i:s')];
            $id = DB::table('pedido_despacho_componentes')->insertGetId($fisica + ['paquete_surtido_id' => $paquete, 'componente_id' => $componente]);
            if ($caso === 'historica') {
                DB::table('pedido_referencias_fisicas')->insert($fisica + ['id' => $id]);
            }
        }
        if ($caso === 'saneada') {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS');
            try {
                (require database_path('migrations/retiro_controlado/2026_10_30_003639_retirar_estructuras_surtidos_vacias.php'))->up();
            } finally {
                putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
            }
        } elseif ($caso === 'parcial') {
            Schema::drop('pedido_despacho_componentes');
        } elseif ($caso === 'vista') {
            DB::unprepared('CREATE VIEW dependencia_cierre AS SELECT id FROM formatos_surtidos');
        } elseif ($caso === 'fk') {
            Schema::create('dependencia_cierre', function (Blueprint $tabla): void {
                $tabla->foreignId('formato_id')->constrained('formatos_surtidos');
            });
        } elseif ($caso === 'trigger') {
            DB::unprepared(DB::getDriverName() === 'mysql'
                ? 'CREATE TRIGGER dependencia_cierre BEFORE UPDATE ON formatos_surtidos FOR EACH ROW SET @dependencia = 1'
                : 'CREATE TRIGGER dependencia_cierre BEFORE UPDATE ON formatos_surtidos BEGIN SELECT 1; END');
        } elseif ($caso === 'referencia') {
            Schema::table('entrega_pedido_detalles', fn (Blueprint $tabla) => $tabla->index('entrega_id', 'entrega_cierre_soporte'));
            Schema::table('entrega_pedido_detalles', fn (Blueprint $tabla) => $tabla->dropUnique('entrega_id_fisica_unica'));
        }
        $antes = $this->fotografiaCierre();
        $esquema = $this->estructuraCierre();
        if (! in_array($caso, ['historica', 'saneada'], true)) {
            try {
                (require database_path('migrations/'.EsquemaSurtidosHistorico::CIERRE))->up();
                $this->fail('La condición insegura debe bloquearse antes de todo DDL.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('No se ha iniciado DDL', $error->getMessage());
            }
            $this->assertSame($antes, $this->fotografiaCierre());
            $this->assertSame($esquema, $this->estructuraCierre());
            $this->assertDatabaseCount('migrations', 49);

            return;
        }
        $this->assertSame(0, Artisan::call('migrate', $opciones));
        $this->assertDatabaseCount('migrations', 50);
        $this->assertCount($caso === 'historica' ? 52 : 46, Schema::getTableListing(null, false));
        $this->assertSame($antes, $this->fotografiaCierre());
        $this->assertSame($esquema, $this->estructuraCierre());
        $this->assertSame(0, Artisan::call('migrate', $opciones));
        $this->assertSame($antes, $this->fotografiaCierre());
        $estado = $caso === 'historica' ? 'historial_conservado' : 'ya_saneado';
        Log::shouldHaveReceived($caso === 'historica' ? 'warning' : 'info')->once()->withArgs(fn (string $mensaje, array $contexto): bool => $contexto['estado_esquema'] === $estado);
        $this->assertTrue(Schema::hasColumns('pedido_detalles', ['tipo_linea', 'retirado_en']));
        $this->assertTrue(Schema::hasTable('pedido_agrupacion_detalles'));
        $this->assertSame(0, Artisan::call('migrate', $opciones + ['--path' => [base_path('tests/migracion_futura_cierre.php')], '--realpath' => true]));
        $this->assertTrue(Schema::hasTable('prueba_actualizacion_futura'));
        $this->assertDatabaseCount('migrations', 51);
    }

    private function fotografiaCierre(): array
    {
        $fotografia = [];
        foreach (Schema::getTableListing(null, false) as $tabla) {
            if ($tabla !== 'migrations') {
                $filas = DB::table($tabla)->get()->map(fn (object $fila): string => json_encode($fila, JSON_THROW_ON_ERROR))->all();
                sort($filas);
                $fotografia[$tabla] = $filas;
            }
        }
        ksort($fotografia);

        return $fotografia;
    }

    private function estructuraCierre(): array
    {
        $estructura = [];
        foreach (Schema::getTableListing(null, false) as $tabla) {
            $estructura[$tabla] = [Schema::getColumns($tabla), Schema::getIndexes($tabla), Schema::getForeignKeys($tabla)];
        }
        ksort($estructura);

        return $estructura;
    }
}
