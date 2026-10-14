<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpresionPedidosPorFechaTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConAcceso(): User
    {
        $rol = Role::create(['nombre' => 'Operaciones Comerciales', 'es_sistema' => true]);
        $permiso = Permiso::create(['nombre' => 'pedidos.ver', 'descripcion' => 'Consultar pedidos']);
        $rol->permisos()->attach($permiso->id);

        return User::create([
            'first_name' => 'Operadora',
            'last_name' => 'Prueba',
            'email' => 'impresion@local.test',
            'password' => 'password123',
            'role_id' => $rol->id,
            'status' => true,
        ]);
    }

    private function crearPedido(User $usuario, string $fecha, string $estado = 'registrado'): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente '.uniqid(), 'estado' => true]);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'fecha_pedido' => $fecha,
            'fecha_entrega' => $fecha,
            'condicion_pago' => 'contado',
            'estado' => $estado,
            'cliente_nombre' => $cliente->nombre,
            'total' => '10.00',
        ]);
    }

    public function test_imprime_todos_los_pedidos_de_una_fecha_en_dos_por_hoja(): void
    {
        $usuario = $this->usuarioConAcceso();
        $this->actingAs($usuario);

        for ($i = 0; $i < 5; $i++) {
            $this->crearPedido($usuario, '2026-10-18');
        }

        $respuesta = $this->get(route('pedidos.imprimir-por-fecha', [
            'fecha_pedido' => '2026-10-18',
        ]));

        $respuesta->assertOk();
        $this->assertSame(3, substr_count($respuesta->getContent(), '<main class="a4-hoja">'));
        $this->assertSame(5, substr_count($respuesta->getContent(), '<section class="nota"'));
        $respuesta->assertSee('000001')->assertSee('000005');
    }

    public function test_no_incluye_cancelados_ni_pedidos_de_otro_dia(): void
    {
        $usuario = $this->usuarioConAcceso();
        $this->actingAs($usuario);

        $permitido = $this->crearPedido($usuario, '2026-10-18', 'confirmado');
        $cancelado = $this->crearPedido($usuario, '2026-10-18', 'cancelado');
        $otroDia = $this->crearPedido($usuario, '2026-10-19');

        $respuesta = $this->get(route('pedidos.imprimir-por-fecha', [
            'fecha_pedido' => '2026-10-18',
        ]));

        $respuesta->assertOk()
            ->assertSee('N.º '.$permitido->numero)
            ->assertDontSee('N.º '.$cancelado->numero)
            ->assertDontSee('N.º '.$otroDia->numero);
    }

    public function test_no_hay_impresion_si_solo_existen_cancelados_esa_fecha(): void
    {
        $usuario = $this->usuarioConAcceso();
        $this->actingAs($usuario);
        $this->crearPedido($usuario, '2026-10-18', 'cancelado');

        $this->get(route('pedidos.imprimir-por-fecha', [
            'fecha_pedido' => '2026-10-18',
        ]))->assertRedirect()->assertSessionHasErrors('fecha_pedido');
    }

    public function test_ruta_antigua_de_dos_pedidos_rechaza_cancelados(): void
    {
        $usuario = $this->usuarioConAcceso();
        $this->actingAs($usuario);
        $valido = $this->crearPedido($usuario, '2026-10-18');
        $cancelado = $this->crearPedido($usuario, '2026-10-18', 'cancelado');

        $this->get(route('pedidos.imprimir-dos', [
            'pedido_1' => $valido->id,
            'pedido_2' => $cancelado->id,
        ]))->assertRedirect()->assertSessionHasErrors('pedidos');
    }

    public function test_ruta_antigua_sigue_imprimiendo_dos_pedidos_no_cancelados(): void
    {
        $usuario = $this->usuarioConAcceso();
        $this->actingAs($usuario);
        $uno = $this->crearPedido($usuario, '2026-10-18');
        $dos = $this->crearPedido($usuario, '2026-10-19', 'confirmado');

        $this->get(route('pedidos.imprimir-dos', [
            'pedido_1' => $uno->id,
            'pedido_2' => $dos->id,
        ]))->assertOk()->assertSee('N.º '.$uno->numero)->assertSee('N.º '.$dos->numero);
    }

    public function test_fecha_es_obligatoria_y_debe_tener_formato_valido(): void
    {
        $this->actingAs($this->usuarioConAcceso());

        $this->get(route('pedidos.imprimir-por-fecha'))
            ->assertRedirect()->assertSessionHasErrors('fecha_pedido');

        $this->get(route('pedidos.imprimir-por-fecha', ['fecha_pedido' => '18-10-2026']))
            ->assertRedirect()->assertSessionHasErrors('fecha_pedido');
    }
}
