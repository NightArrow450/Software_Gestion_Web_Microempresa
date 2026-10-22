<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Insumo;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuditoriaPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaPersistenteTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        $rol = Role::create(['nombre' => 'Administrador', 'es_sistema' => true]);
        foreach (['inventario.ver', 'inventario.ajustar'] as $nombre) {
            $rol->permisos()->attach(Permiso::create(['nombre' => $nombre, 'descripcion' => $nombre])->id);
        }
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'auditoria@test.local', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
        $this->seed(AuditoriaPermisoSeeder::class);

        return $usuario;
    }

    public function test_ajuste_auditado_y_consulta_administrativa_filtrada(): void
    {
        $usuario = $this->administrador();
        $this->seed(AuditoriaPermisoSeeder::class);
        $this->assertSame(1, Permiso::where('nombre', 'auditoria.ver')->count());
        $insumo = Insumo::create(['codigo' => 'AUD', 'nombre' => 'Envase', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 10]);
        $this->actingAs($usuario)->post(route('inventario.movimientos.store'), ['insumo_id' => $insumo->id, 'tipo' => 'ajuste', 'cantidad' => 8, 'motivo' => 'Conteo físico de prueba'])->assertRedirect();
        $this->assertDatabaseHas('auditoria', ['usuario_id' => $usuario->id, 'accion' => 'stock_ajuste', 'entidad' => 'insumo', 'entidad_id' => $insumo->id]);
        $registro = Auditoria::firstOrFail();
        $this->assertSame('10.000', $registro->detalle['stock_anterior']);
        $this->get(route('auditoria.index', ['accion' => 'stock_ajuste']))->assertOk()->assertSee('stock_ajuste');
        $this->get(route('auditoria.index', ['accion' => 'inexistente']))->assertOk()->assertSee('No hay acciones');
        $this->expectException(\LogicException::class);
        $registro->delete();
    }

    public function test_fallo_de_auditoria_revierte_stock_y_movimiento(): void
    {
        $usuario = $this->administrador();
        $insumo = Insumo::create(['codigo' => 'FALLO', 'nombre' => 'Envase', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 10]);
        DB::unprepared("CREATE TRIGGER impedir_auditoria BEFORE INSERT ON auditoria BEGIN SELECT RAISE(ABORT, 'Fallo simulado'); END");
        $this->actingAs($usuario)->post(route('inventario.movimientos.store'), ['insumo_id' => $insumo->id, 'tipo' => 'ajuste', 'cantidad' => 8, 'motivo' => 'Conteo físico de prueba'])->assertStatus(500);
        $this->assertSame('10.000', $insumo->fresh()->cantidad_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('solicitudes_idempotentes', 0);
    }

    public function test_otro_rol_no_consulta_auditoria_aunque_tenga_permiso(): void
    {
        $usuario = $this->administrador();
        $usuario->role->update(['nombre' => 'Operaciones Comerciales']);
        $this->actingAs($usuario)->get(route('auditoria.index'))->assertForbidden();
    }

    public function test_cambio_de_usuario_no_guarda_hash_ni_password(): void
    {
        $usuario = $this->administrador();
        $this->actingAs($usuario);
        $usuario->update(['password' => 'OtraClaveSegura123', 'first_name' => 'Ana María']);
        $detalle = Auditoria::firstOrFail()->getRawOriginal('detalle');
        $this->assertStringNotContainsString('OtraClave', $detalle);
        $this->assertStringNotContainsString($usuario->password, $detalle);
        $this->assertStringContainsString('first_name', $detalle);
    }
}
