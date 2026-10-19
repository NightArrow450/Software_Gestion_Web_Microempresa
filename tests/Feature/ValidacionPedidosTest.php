<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidacionPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(): array
    {
        $this->travelTo(Carbon::parse('2026-10-19 02:00:00', 'UTC'));
        $rol = Role::create(['nombre' => 'Operaciones Comerciales']);
        foreach (['pedidos.crear', 'pedidos.editar', 'pedidos.cancelar'] as $nombre) {
            $rol->permisos()->attach(Permiso::firstOrCreate(['nombre' => $nombre])->id);
        }
        $this->actingAs(User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'pedidos@local.test', 'password' => 'Prueba12345', 'role_id' => $rol->id, 'status' => true]));
        $cliente = Cliente::create(['nombre' => 'Cliente Prueba', 'estado' => true]);
        $categoria = Categoria::create(['nombre' => 'Limpieza']);
        $producto = Producto::create(['nombre' => 'Lejía', 'codigo' => 'PRO-1', 'categoria_id' => $categoria->id, 'estado' => true]);
        $presentacion = PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => 'PRO-1-L', 'contenido' => 1, 'unidad_medida' => 'L', 'precio_unitario' => 2, 'venta_por_unidad' => true, 'venta_por_empaque' => false, 'estado' => true]);

        return ['cliente_id' => $cliente->id, 'fecha_pedido' => '2026-10-18', 'fecha_entrega' => '2026-10-18', 'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $presentacion->id, 'modalidad' => 'unidad', 'cantidad' => 1]]];
    }

    public static function limitesValidos(): array
    {
        return [
            'hoy en Lima' => ['2026-10-18', '2026-10-18', 1],
            'treinta días' => ['2026-09-18', '2026-09-18', 1],
            'noventa días y cantidad máxima' => ['2026-10-18', '2027-01-16', 10000],
        ];
    }

    #[DataProvider('limitesValidos')]
    public function test_acepta_fechas_y_cantidad_en_los_limites(string $pedido, string $entrega, int $cantidad): void
    {
        $datos = $this->escenario();
        $datos['fecha_pedido'] = $pedido;
        $datos['fecha_entrega'] = $entrega;
        $datos['detalles'][0]['cantidad'] = $cantidad;

        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseCount('pedidos', 1);
        $this->assertDatabaseHas('pedido_detalles', ['cantidad' => $cantidad]);
    }

    public static function limitesInvalidos(): array
    {
        return [
            'mañana UTC pero hoy no en Lima' => ['fecha_pedido', '2026-10-19'],
            'treinta y un días' => ['fecha_pedido', '2026-09-17'],
            'fecha inexistente' => ['fecha_pedido', '2026-02-30'],
            'entrega antes' => ['fecha_entrega', '2026-10-17'],
            'noventa y un días' => ['fecha_entrega', '2027-01-17'],
            'cantidad excesiva' => ['detalles.0.cantidad', 10001],
        ];
    }

    #[DataProvider('limitesInvalidos')]
    public function test_rechaza_fechas_y_cantidad_fuera_de_limites(string $campo, string|int $valor): void
    {
        $datos = $this->escenario();
        data_set($datos, $campo, $valor);

        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors($campo);

        $this->assertDatabaseCount('pedidos', 0);
        $this->assertDatabaseCount('pedido_detalles', 0);
    }

    public function test_editar_aplica_el_limite_sin_alterar_el_pedido(): void
    {
        $datos = $this->escenario();
        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $datos['fecha_entrega'] = '2027-01-17';

        $this->put(route('pedidos.update', $pedido), $datos)->assertSessionHasErrors('fecha_entrega');

        $this->assertSame('2026-10-18', $pedido->fresh()->fecha_entrega->toDateString());
    }

    public static function motivos(): array
    {
        return [
            'observar mínimo' => ['observar', 10, true],
            'observar máximo' => ['observar', 1000, true],
            'observar corto' => ['observar', 9, false],
            'observar largo' => ['observar', 1001, false],
            'cancelar mínimo' => ['cancelar', 10, true],
            'cancelar máximo' => ['cancelar', 1000, true],
            'cancelar corto' => ['cancelar', 9, false],
            'cancelar largo' => ['cancelar', 1001, false],
        ];
    }

    #[DataProvider('motivos')]
    public function test_valida_longitud_del_motivo_y_conserva_transiciones(string $accion, int $longitud, bool $valido): void
    {
        $datos = $this->escenario();
        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();

        $respuesta = $this->post(route($accion === 'cancelar' ? 'pedidos.cancelar' : 'pedidos.procesar', $pedido), ['accion' => $accion, 'motivo' => str_repeat('a', $longitud)]);

        if ($valido) {
            $respuesta->assertSessionHasNoErrors();
            $this->assertSame($accion === 'cancelar' ? 'cancelado' : 'observado', $pedido->fresh()->estado);
            $this->assertDatabaseCount('pedido_estados_historial', 2);
        } else {
            $respuesta->assertSessionHasErrors('motivo');
            $this->assertSame('registrado', $pedido->fresh()->estado);
            $this->assertDatabaseCount('pedido_estados_historial', 1);
        }
    }
}
