<?php
namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultaEstadoPedidoTest extends TestCase
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

    public function test_puede_buscar_por_codigo_formateado_y_consultar_estado(): void
    {
        $u = $this->usuario(['seguimiento.ver']);
        $pedido = $this->pedido($u, 'Cliente Test', '2026-10-17', 'despachado');
        $this->actingAs($u)->get(route('seguimiento.index', ['codigo' => $pedido->numero]))
            ->assertOk()->assertSee($pedido->numero)->assertSee('Cliente Test');
        $this->actingAs($u)->get(route('seguimiento.show', $pedido))
            ->assertOk()->assertSee('Despachado')->assertSee('Entrega prevista');
    }

    public function test_busqueda_por_cliente_y_resultados_no_coincidentes(): void
    {
        $u = $this->usuario(['seguimiento.ver']);
        $this->pedido($u, 'Rosa Flores');
        $this->pedido($u, 'Carlos Núñez');
        $this->actingAs($u)->get(route('seguimiento.index', ['cliente'=>'Rosa']))
            ->assertOk()->assertSee('Rosa Flores')->assertDontSee('Carlos Núñez');
        $this->actingAs($u)->get(route('seguimiento.index', ['codigo'=>'999999']))
            ->assertOk()->assertSee('No se encontraron pedidos');
    }

    public function test_sin_permiso_o_sesion_no_puede_ver_seguimiento(): void
    {
        $u = $this->usuario([]);
        $p = $this->pedido($u);
        $this->get(route('seguimiento.index'))->assertRedirect(route('login'));
        $this->actingAs($u)->get(route('seguimiento.index'))->assertForbidden();
        $this->actingAs($u)->get(route('seguimiento.show', $p))->assertForbidden();
    }
}
