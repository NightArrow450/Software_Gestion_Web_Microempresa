<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use App\Services\TokenOperacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotenciaTest extends TestCase
{
    use RefreshDatabase;

    private function preparar(): array
    {
        $rol = Role::create(['nombre' => 'Prueba', 'es_sistema' => false]);
        foreach (['inventario.ver', 'inventario.entrada'] as $permiso) {
            $rol->permisos()->attach(Permiso::create(['nombre' => $permiso, 'descripcion' => $permiso])->id);
        }
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'idempotencia@test.local', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
        $insumo = Insumo::create(['codigo' => 'ENV', 'nombre' => 'Envase', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 0]);
        $this->actingAs($usuario);

        return ['insumo_id' => $insumo->id, 'tipo' => 'entrada', 'cantidad' => 5, 'motivo' => 'Ingreso de prueba', '_token_operacion' => TokenOperacion::emitir($usuario->id)];
    }

    public function test_doble_envio_crea_un_solo_movimiento(): void
    {
        $datos = $this->preparar();
        $this->post(route('inventario.movimientos.store'), $datos)->assertRedirect();
        $this->post(route('inventario.movimientos.store'), $datos)->assertRedirect();
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseCount('solicitudes_idempotentes', 1);
        $this->assertDatabaseHas('insumos', ['id' => $datos['insumo_id'], 'cantidad_actual' => 5]);
        $datos['cantidad'] = 10;
        $this->post(route('inventario.movimientos.store'), $datos)->assertSessionHasErrors('operacion');
    }

    public function test_validacion_no_consume_token_y_ausencia_se_rechaza(): void
    {
        $datos = $this->preparar();
        $this->post(route('inventario.movimientos.store'), array_replace($datos, ['cantidad' => -1]))->assertSessionHasErrors('cantidad');
        $this->assertDatabaseCount('solicitudes_idempotentes', 0);
        $this->post(route('inventario.movimientos.store'), $datos)->assertRedirect();
        $this->post(route('inventario.movimientos.store'), array_replace($datos, ['_token_operacion' => null]))->assertSessionHasErrors('operacion');
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_token_de_otro_usuario_no_es_aceptado(): void
    {
        $datos = $this->preparar();
        $datos['_token_operacion'] = TokenOperacion::emitir(99999);
        $this->post(route('inventario.movimientos.store'), $datos)->assertSessionHasErrors('operacion');
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
