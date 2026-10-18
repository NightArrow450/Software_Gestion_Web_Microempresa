<?php
namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorialPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create(['nombre' => 'Producción/Reparto', 'es_sistema' => true]);
        foreach ($permisos as $nombre) {
            $permiso = Permiso::firstOrCreate(
                ['nombre' => $nombre], ['descripcion' => $nombre]
            );
            $rol->permisos()->attach($permiso->id);
        }
        return User::create([
            'first_name' => 'María', 'last_name' => 'Operadora',
            'email' => 'seguimiento_'.uniqid().'@local.test',
            'password' => 'password123', 'role_id' => $rol->id, 'status' => true,
        ]);
    }

    private function pedido(User $usuario, string $nombre = 'Cliente Uno',
        string $fecha = '2026-10-17', string $estado = 'confirmado'): Pedido
    {
        $cliente = Cliente::create(['nombre' => $nombre, 'estado' => true]);
        return Pedido::create([
            'cliente_id' => $cliente->id, 'usuario_id' => $usuario->id,
            'fecha_pedido' => $fecha, 'fecha_entrega' => '2026-10-20',
            'condicion_pago' => 'contado', 'estado' => $estado,
            'total' => '20.00', 'cliente_nombre' => $nombre,
        ]);
    }

    public function test_filtro_por_rango_fecha_cliente_y_estado_incluye_cancelados(): void
    {
        $u = $this->usuario(['seguimiento.ver']);
        $elegido = $this->pedido($u, 'María Cliente', '2026-10-18', 'cancelado');
        $this->pedido($u, 'Cliente Otro', '2026-10-18', 'confirmado');
        $this->pedido($u, 'María Cliente', '2026-10-10', 'cancelado');
        $r = $this->actingAs($u)->get(route('seguimiento.historial', [
            'fecha_desde'=>'2026-10-17','fecha_hasta'=>'2026-10-19',
            'cliente'=>'María', 'estado'=>'cancelado',
        ]));
        $r->assertOk()->assertSee($elegido->numero)->assertSee('Cancelado');
        $r->assertSee('Resultados: 1 pedido(s).');
        $r->assertSee(route('seguimiento.show', $elegido));
    }

    public function test_historial_pagina_resultados_y_sin_coincidencias(): void
    {
        $u = $this->usuario(['seguimiento.ver']);
        for ($i=0; $i<18; $i++) $this->pedido($u, 'Cliente '.($i+1));
        $this->actingAs($u)->get(route('seguimiento.historial'))
            ->assertOk()->assertSee('Resultados: 18 pedido(s).')->assertSee('page=2');
        $this->actingAs($u)->get(route('seguimiento.historial', ['cliente'=>'INEXISTENTE']))
            ->assertOk()->assertSee('No se encontraron pedidos');
    }

    public function test_fecha_fin_antes_de_inicio_es_rechazada(): void
    {
        $u = $this->usuario(['seguimiento.ver']);
        $this->actingAs($u)->get(route('seguimiento.historial', [
            'fecha_desde'=>'2026-10-18', 'fecha_hasta'=>'2026-10-17',
        ]))->assertSessionHasErrors('fecha_hasta');
    }

    public function test_usuario_sin_permiso_no_ve_el_historial(): void
    {
        $u = $this->usuario([]);
        $this->actingAs($u)->get(route('seguimiento.historial'))->assertForbidden();
    }
}
