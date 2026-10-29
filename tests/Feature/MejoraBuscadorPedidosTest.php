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

class MejoraBuscadorPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos): User
    {
        $rol = Role::create(['nombre' => 'Operaciones Comerciales', 'es_sistema' => true]);
        foreach ($permisos as $nombre) {
            $permiso = Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre]);
            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'Pruebas', 'last_name' => 'Ventas',
            'email' => 'ventas.autocompletar@example.com',
            'password' => 'password123', 'role_id' => $rol->id, 'status' => true,
        ]);
    }

    private function datosCatalogo(): array
    {
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00', 'America/Lima'));
        $cliente = Cliente::create(['nombre' => 'Bodega La Unión', 'estado' => true]);
        $categoria = Categoria::create(['nombre' => 'Limpieza', 'estado' => true]);
        $producto = Producto::create([
            'codigo' => 'QUITA-01', 'categoria_id' => $categoria->id,
            'nombre' => 'Quitamanchas', 'estado' => true,
        ]);
        $presentacion = PresentacionProducto::create([
            'producto_id' => $producto->id, 'codigo_sku' => 'QUIT-500',
            'tipo_envase' => 'Botella', 'contenido' => 500, 'unidad_medida' => 'ml',
            'tipo_empaque' => 'Caja', 'unidades_por_empaque' => 4,
            'venta_por_unidad' => true, 'venta_por_empaque' => true,
            'precio_unitario' => '8.50', 'precio_empaque' => '34.00', 'estado' => true,
        ]);

        return [$cliente, $producto, $presentacion];
    }

    private function pedidoDatos(int $clienteId, int $presentacionId): array
    {
        return [
            'cliente_id' => $clienteId,
            'fecha_pedido' => '2026-10-18',
            'fecha_entrega' => '2026-10-19',
            'condicion_pago' => 'contado',
            'detalles' => [[
                'presentacion_producto_id' => $presentacionId,
                'modalidad' => 'unidad', 'cantidad' => 2,
            ]],
        ];
    }

    public function test_servidor_ignora_precios_y_total_manipulados(): void
    {
        $this->actingAs($this->usuario(['pedidos.crear', 'pedidos.ver']));
        [$cliente, , $presentacion] = $this->datosCatalogo();
        $datos = $this->pedidoDatos($cliente->id, $presentacion->id);
        $datos['total'] = '0.01';
        $datos['detalles'][0]['precio_unitario'] = '0.01';
        $datos['detalles'][0]['importe'] = '0.01';

        $this->post(route('pedidos.store'), $datos)->assertRedirect();
        $this->assertDatabaseHas('pedidos', ['total' => '17.00']);
        $this->assertDatabaseHas('pedido_detalles', [
            'precio_unitario' => '8.50', 'importe' => '17.00',
        ]);
    }

    public function test_servidor_rechaza_filas_duplicadas_aunque_se_altere_el_navegador(): void
    {
        $this->actingAs($this->usuario(['pedidos.crear']));
        [$cliente, , $presentacion] = $this->datosCatalogo();
        $datos = $this->pedidoDatos($cliente->id, $presentacion->id);
        $datos['detalles'][] = $datos['detalles'][0];
        $this->post(route('pedidos.store'), $datos)
            ->assertSessionHasErrors('detalles.1.presentacion_producto_id');
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_busqueda_paginada_y_edicion_versionada_tambien_en_confirmados(): void
    {
        $this->actingAs($this->usuario(['pedidos.crear', 'pedidos.editar', 'pedidos.ver']));
        [$cliente, , $presentacion] = $this->datosCatalogo();
        $this->get(route('pedidos.create'))->assertOk()->assertSee('data-buscar-producto');

        $datos = $this->pedidoDatos($cliente->id, $presentacion->id);
        $this->post(route('pedidos.store'), $datos)->assertRedirect();
        $pedido = Pedido::firstOrFail();
        $this->get(route('pedidos.edit', $pedido))
            ->assertOk()->assertSee('data-buscar-producto')->assertSee('QUIT-500');

        $pedido->update(['estado' => 'confirmado']);
        $this->get(route('pedidos.edit', $pedido))->assertOk();
        $datos['motivo'] = 'Revisión comercial autorizada después de confirmar.';
        $this->put(route('pedidos.update', $pedido), $datos)
            ->assertSessionHasNoErrors();
    }

    public function test_catalogo_de_sugerencias_excluye_inactivos_y_sin_precio(): void
    {
        $this->actingAs($this->usuario(['pedidos.crear']));
        [, $producto, $activa] = $this->datosCatalogo();
        $sinPrecio = $activa->replicate();
        $sinPrecio->codigo_sku = 'SIN-PRECIO';
        $sinPrecio->precio_unitario = null;
        $sinPrecio->precio_empaque = null;
        $sinPrecio->save();
        $inactiva = $activa->replicate();
        $inactiva->codigo_sku = 'SKU-INACTIVO';
        $inactiva->estado = false;
        $inactiva->save();

        $respuesta = $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'skus', 'producto_id' => $producto->id]))->assertOk();
        $respuesta->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $activa->id)->assertJsonPath('data.1.precio_unitario', null);
        $respuesta->assertDontSee('SKU-INACTIVO');
        $producto->update(['estado' => false]);
        $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'skus', 'producto_id' => $producto->id]))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rutas_de_pedidos_no_permiten_acceso_sin_permiso(): void
    {
        $this->actingAs($this->usuario(['pedidos.ver']));
        [$cliente, , $presentacion] = $this->datosCatalogo();
        $this->get(route('pedidos.create'))->assertForbidden();
        $this->post(route('pedidos.store'), $this->pedidoDatos($cliente->id, $presentacion->id))->assertForbidden();
    }

    public function test_buscador_de_clientes_solo_recibe_clientes_activos(): void
    {
        $this->actingAs($this->usuario(['pedidos.crear']));
        [$cliente] = $this->datosCatalogo();
        Cliente::create(['nombre' => 'Cliente Inactivo', 'estado' => false]);
        $this->get(route('pedidos.create'))
            ->assertOk()
            ->assertSee('data-buscar-cliente');
        $this->getJson(route('pedidos.unidades.catalogo', ['tipo' => 'clientes']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $cliente->id);
    }

    public function test_detalle_del_pedido_incluye_boton_para_copiar_resumen_sin_enviar_mensaje(): void
    {
        $this->actingAs($this->usuario(['pedidos.crear', 'pedidos.ver']));
        [$cliente, , $presentacion] = $this->datosCatalogo();
        $this->post(route('pedidos.store'), $this->pedidoDatos($cliente->id, $presentacion->id))->assertRedirect();
        $this->get(route('pedidos.show', Pedido::firstOrFail()))
            ->assertOk()
            ->assertSee('copiar-resumen-whatsapp')
            ->assertSee('Copiar resumen para WhatsApp');
    }
}
