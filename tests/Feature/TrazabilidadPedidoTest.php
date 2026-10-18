<?php
namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrazabilidadPedidoTest extends TestCase
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

    public function test_eventos_aparecen_en_orden_con_responsable_y_motivo(): void
    {
        $u = $this->usuario(['seguimiento.ver']);
        $p = $this->pedido($u, 'Elena Díaz', '2026-10-17', 'despachado');
        $p->historialEstados()->create([
            'usuario_id'=>$u->id, 'estado_anterior'=>null,
            'estado_nuevo'=>'registrado', 'motivo'=>'Registrado inicialmente',
        ]);
        $p->historialEstados()->create([
            'usuario_id'=>$u->id, 'estado_anterior'=>'registrado',
            'estado_nuevo'=>'confirmado', 'motivo'=>'Confirmación comercial',
        ]);
        $p->historialEstados()->create([
            'usuario_id'=>$u->id, 'estado_anterior'=>'listo_despacho',
            'estado_nuevo'=>'despachado', 'motivo'=>'Salida de reparto',
        ]);
        $respuesta = $this->actingAs($u)->get(route('seguimiento.trazabilidad', $p));
        $respuesta->assertOk()->assertSee('María')->assertSee('Salida de reparto');
        $respuesta->assertSeeInOrder(['Registrado inicialmente', 'Confirmación comercial', 'Salida de reparto']);
    }

    public function test_trazabilidad_no_mezcla_eventos_de_otros_pedidos(): void
    {
        $u = $this->usuario(['seguimiento.ver']);
        $p1 = $this->pedido($u, 'Cliente A');
        $p2 = $this->pedido($u, 'Cliente B');
        $p1->historialEstados()->create(['usuario_id'=>$u->id, 'estado_nuevo'=>'confirmado', 'motivo'=>'Pedido propio']);
        $p2->historialEstados()->create(['usuario_id'=>$u->id, 'estado_nuevo'=>'observado', 'motivo'=>'SOLO SEGUNDO PEDIDO']);
        $this->actingAs($u)->get(route('seguimiento.trazabilidad', $p1))
            ->assertOk()->assertSee('Pedido propio')->assertDontSee('SOLO SEGUNDO PEDIDO');
        $this->assertDatabaseCount('pedido_estados_historial', 2);
    }

    public function test_trazabilidad_solo_lectura_y_permisos(): void
    {
        $u = $this->usuario([]);
        $p = $this->pedido($u);
        $this->actingAs($u)->get(route('seguimiento.trazabilidad', $p))->assertForbidden();
        // Solo existe ruta GET: un POST no puede alterar el historial.
        $u->role->permisos()->attach(Permiso::firstOrCreate(['nombre'=>'seguimiento.ver'], ['descripcion'=>'seguimiento.ver'])->id);
        $this->actingAs($u)->post(route('seguimiento.trazabilidad', $p), ['estado_nuevo'=>'entregado'])
            ->assertStatus(405);
        $this->assertDatabaseCount('pedido_estados_historial', 0);
    }
}
