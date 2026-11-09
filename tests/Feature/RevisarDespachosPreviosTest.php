<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisarDespachosPreviosTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_despachados_y_entregados_sin_escribir_datos(): void
    {
        $rol = Role::create(['nombre' => 'Prueba', 'es_sistema' => false]);
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'despachos@test.local', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
        $cliente = Cliente::create(['nombre' => 'Cliente prueba', 'estado' => true]);
        foreach (['confirmado', 'despachado', 'entregado'] as $estado) {
            Pedido::create(['usuario_id' => $usuario->id, 'cliente_id' => $cliente->id, 'cliente_nombre' => $cliente->nombre, 'fecha_pedido' => '2026-10-22', 'fecha_entrega' => '2026-10-22', 'condicion_pago' => 'contado', 'estado' => $estado, 'total' => 0]);
        }
        $antes = DB::table('pedidos')->get()->toJson();
        $this->artisan('inventario:revisar-despachos-previos')->expectsOutputToContain('Pedidos sin salida vinculada: 2')->assertSuccessful();
        $this->assertSame($antes, DB::table('pedidos')->get()->toJson());
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
