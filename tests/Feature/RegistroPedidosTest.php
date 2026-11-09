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
use Tests\TestCase;

class RegistroPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConPermisos(array $nombres): User
    {
        $rol = Role::create([
            'nombre' => 'Operaciones Comerciales',
            'es_sistema' => true,
        ]);

        foreach ($nombres as $nombre) {
            $permiso = Permiso::create([
                'nombre' => $nombre,
                'descripcion' => $nombre,
            ]);

            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'Vendedor',
            'last_name' => 'Prueba',
            'email' => 'vendedor@example.com',
            'password' => 'password123',
            'role_id' => $rol->id,
            'status' => true,
        ]);
    }

    private function datosBase(): array
    {
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00', 'America/Lima'));
        $cliente = Cliente::create([
            'nombre' => 'Bodega Central',
            'estado' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Limpieza',
            'estado' => true,
        ]);

        $producto = Producto::create([
            'codigo' => 'LIMP-001',
            'categoria_id' => $categoria->id,
            'nombre' => 'Limpia Vidrios',
            'estado' => true,
        ]);

        $presentacion = PresentacionProducto::create([
            'producto_id' => $producto->id,
            'codigo_sku' => 'LIMP-900',
            'tipo_envase' => 'Botella',
            'contenido' => 900,
            'unidad_medida' => 'ml',
            'tipo_empaque' => 'Caja',
            'unidades_por_empaque' => 12,
            'venta_por_unidad' => true,
            'venta_por_empaque' => true,
            'precio_unitario' => '4.50',
            'precio_empaque' => '1.00',
            'estado' => true,
        ]);

        return [$cliente, $presentacion];
    }

    private function solicitud(
        int $clienteId,
        int $presentacionId,
        string $modalidad = 'unidad',
        int $cantidad = 2
    ): array {
        return [
            'cliente_id' => $clienteId,
            'fecha_pedido' => '2026-10-12',
            'fecha_entrega' => '2026-10-13',
            'condicion_pago' => 'contado',

            'detalles' => [
                [
                    'presentacion_producto_id' => $presentacionId,
                    'modalidad' => $modalidad,
                    'cantidad' => $cantidad,
                ],
            ],
        ];
    }

    public function test_ajusta_historico_y_conserva_precio_pactado_de_cajas(): void
    {
        $usuario = $this->usuarioConPermisos([
            'pedidos.crear',
            'pedidos.ver',
            'pedidos.editar',
        ]);

        [$cliente, $presentacion] = $this->datosBase();

        $this->actingAs($usuario);

        $pedido = Pedido::create(['cliente_id' => $cliente->id, 'usuario_id' => $usuario->id, 'cliente_nombre' => $cliente->nombre,
            'fecha_pedido' => '2026-10-12', 'fecha_entrega' => '2026-10-13', 'condicion_pago' => 'contado', 'estado' => 'registrado', 'total' => '108.00']);
        $detalle = $pedido->detalles()->create(['presentacion_producto_id' => $presentacion->id, 'codigo_sku' => $presentacion->codigo_sku,
            'descripcion_producto' => 'Limpia Vidrios histórico', 'modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'cantidad' => 2, 'precio_unitario' => '54.00', 'importe' => '108.00']);
        $pedido->historialEstados()->create(['usuario_id' => $usuario->id, 'estado_nuevo' => 'registrado', 'motivo' => 'Fotografía anterior al rediseño.']);

        $respuesta = $this->put(
            route('pedidos.update', $pedido),
            array_merge(
                $this->solicitud(
                    $cliente->id,
                    $presentacion->id,
                    'empaque',
                    2
                ),
                ['total' => '0.01', 'motivo' => 'Se conserva el precio histórico sin aceptar el total manipulado.']
            )
        );

        $respuesta->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pedidos', [
            'id' => 1,
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'total' => '108.00',
            'estado' => 'registrado',
        ]);

        $this->assertDatabaseHas('pedido_detalles', [
            'cantidad' => 2,
            'modalidad' => 'empaque',
            'precio_unitario' => '54.00',
            'importe' => '108.00',
        ]);

        $this->assertDatabaseHas('pedido_estados_historial', [
            'estado_anterior' => null,
            'estado_nuevo' => 'registrado',
        ]);

        $this->assertSame(
            '000001',
            Pedido::firstOrFail()->numero
        );
    }

    public function test_rechaza_cantidad_cero(): void
    {
        $this->actingAs(
            $this->usuarioConPermisos(['pedidos.crear'])
        );

        [$cliente, $presentacion] = $this->datosBase();

        $this->post(
            route('pedidos.store'),
            $this->solicitud(
                $cliente->id,
                $presentacion->id,
                'unidad',
                0
            )
        )->assertSessionHasErrors('detalles.0.cantidad');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_rechaza_cliente_inactivo(): void
    {
        $this->actingAs(
            $this->usuarioConPermisos(['pedidos.crear'])
        );

        [$cliente, $presentacion] = $this->datosBase();

        $cliente->update([
            'estado' => false,
        ]);

        $this->post(
            route('pedidos.store'),
            $this->solicitud(
                $cliente->id,
                $presentacion->id
            )
        )->assertSessionHasErrors('cliente_id');
    }

    public function test_no_permite_registrar_sin_permiso(): void
    {
        $this->actingAs(
            $this->usuarioConPermisos(['pedidos.ver'])
        );

        [$cliente, $presentacion] = $this->datosBase();

        $this->post(
            route('pedidos.store'),
            $this->solicitud(
                $cliente->id,
                $presentacion->id
            )
        )->assertForbidden();
    }

    public function test_dos_pedidos_tienen_numeros_distintos_y_pueden_imprimirse(): void
    {
        $this->actingAs(
            $this->usuarioConPermisos([
                'pedidos.crear',
                'pedidos.ver',
            ])
        );

        [$cliente, $presentacion] = $this->datosBase();

        $datos = $this->solicitud(
            $cliente->id,
            $presentacion->id
        );

        $this->post(
            route('pedidos.store'),
            $datos
        )->assertRedirect();

        $this->post(
            route('pedidos.store'),
            $datos
        )->assertRedirect();

        $this->assertSame(
            ['000001', '000002'],
            Pedido::orderBy('id')
                ->get()
                ->map(fn ($pedido) => $pedido->numero)
                ->all()
        );

        $this->get(
            route('pedidos.imprimir-dos', [
                'pedido_1' => 1,
                'pedido_2' => 2,
            ])
        )
            ->assertOk()
            ->assertSee('000001')
            ->assertSee('000002');
    }
}
