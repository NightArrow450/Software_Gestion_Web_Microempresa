<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Role;
use App\Models\User;
use App\Services\VerificadorInventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerificarInventarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_inicial_sin_movimientos_no_es_error_y_comando_no_escribe(): void
    {
        $insumo = Insumo::create(['codigo' => 'INICIAL', 'nombre' => 'Envase', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => '12.500']);
        $antes = DB::table('insumos')->get()->toJson();
        $reporte = app(VerificadorInventario::class)->revisar();
        $this->assertTrue($reporte['correcto']);
        $this->assertCount(1, $reporte['sin_historial']);
        $this->artisan('inventario:verificar', ['--json' => true])->expectsOutput(json_encode($reporte, JSON_UNESCAPED_UNICODE))->assertSuccessful();
        $this->assertSame($antes, DB::table('insumos')->get()->toJson());
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_base_implicita_y_cadena_rota_se_distinguen_sin_corregir(): void
    {
        $rol = Role::create(['nombre' => 'Prueba', 'es_sistema' => false]);
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'verificar@test.local', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
        $insumo = Insumo::create(['codigo' => 'CADENA', 'nombre' => 'Envase', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 15]);
        $datos = ['insumo_id' => $insumo->id, 'usuario_id' => $usuario->id, 'tipo' => 'entrada', 'cantidad' => 5, 'stock_anterior' => 10, 'stock_nuevo' => 15, 'motivo' => 'Historial previo'];
        MovimientoInventario::create($datos);
        $reporte = app(VerificadorInventario::class)->revisar();
        $this->assertTrue($reporte['correcto']);
        $this->assertCount(1, $reporte['saldo_inicial']);
        MovimientoInventario::create(array_replace($datos, ['tipo' => 'futuro', 'stock_anterior' => 14, 'stock_nuevo' => 16]));
        $antes = DB::table('movimientos_inventario')->get()->toJson();
        $reporte = app(VerificadorInventario::class)->revisar();
        $this->assertFalse($reporte['correcto']);
        $this->assertCount(2, $reporte['inconsistencias'][0]['problemas']);
        $this->artisan('inventario:verificar')->assertFailed();
        $this->assertSame($antes, DB::table('movimientos_inventario')->get()->toJson());
        $this->assertSame('15.000', $insumo->fresh()->cantidad_actual);
    }

    public function test_verifica_continuidad_entre_lotes_y_conserva_orden_y_saldos(): void
    {
        $rol = Role::create(['nombre' => 'Prueba', 'es_sistema' => false]);
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'lotes@test.local', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
        $primero = Insumo::create(['codigo' => 'PRIMERO', 'nombre' => 'Envase', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => '300.500']);
        $segundo = Insumo::create(['codigo' => 'SEGUNDO', 'nombre' => 'Etiqueta', 'categoria' => 'Etiquetas', 'unidad_medida' => 'unidad', 'cantidad_actual' => '300.500']);
        $movimientos = [];
        for ($i = 0; $i < 601; $i++) {
            $insumo = $i % 2 === 0 ? $segundo : $primero;
            $anterior = intdiv($i, 2);
            $movimientos[] = ['origen_tipo' => 'manual', 'origen_id' => (string) Str::ulid(), 'insumo_id' => $insumo->id, 'usuario_id' => $usuario->id, 'tipo' => 'entrada', 'cantidad' => 1, 'stock_anterior' => $i === 500 ? 249.5 : $anterior + 0.5, 'stock_nuevo' => $anterior + 1.5, 'motivo' => 'Historial independiente para verificar continuidad.'];
        }
        DB::table('movimientos_inventario')->insert($movimientos);
        $antes = DB::table('movimientos_inventario')->orderBy('id')->get()->toJson();
        $reporte = app(VerificadorInventario::class)->revisar();
        $this->assertFalse($reporte['correcto']);
        $this->assertSame($primero->id, $reporte['saldo_inicial'][0]['id']);
        $this->assertSame(0.5, $reporte['saldo_inicial'][0]['saldo_inicial']);
        $this->assertSame(300.5, $reporte['saldo_inicial'][0]['saldo_final_historial']);
        $this->assertSame($segundo->id, $reporte['inconsistencias'][0]['id']);
        $this->assertSame(301.5, $reporte['inconsistencias'][0]['saldo_final_historial']);
        $this->assertSame([
            ['movimiento_id' => DB::table('movimientos_inventario')->orderBy('id')->skip(500)->value('id'), 'problema' => 'Cadena de saldos rota'],
            ['problema' => 'El saldo actual no coincide con el último movimiento'],
        ], $reporte['inconsistencias'][0]['problemas']);
        $this->assertSame($antes, DB::table('movimientos_inventario')->orderBy('id')->get()->toJson());
        $this->assertSame('300.500', $segundo->fresh()->cantidad_actual);
    }
}
