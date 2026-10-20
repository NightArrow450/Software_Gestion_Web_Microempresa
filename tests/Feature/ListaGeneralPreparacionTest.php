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
use Tests\TestCase;

class ListaGeneralPreparacionTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos = ['pedidos.ver']): User
    {
        $rol = Role::create(['nombre' => 'Producción/Reparto', 'es_sistema' => true]);
        foreach ($permisos as $nombre) {
            $permiso = Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre]);
            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'Operador', 'last_name' => 'Prueba',
            'email' => 'operador@lista.test', 'password' => 'password123',
            'role_id' => $rol->id, 'status' => true,
        ]);
    }

    private function presentacion(string $sku, int $unidades = 4, string $nombre = 'Lejía'): PresentacionProducto
    {
        $categoria = Categoria::firstOrCreate(['nombre' => 'Limpieza'], ['estado' => true]);
        $producto = Producto::create([
            'codigo' => 'COD-' . $sku, 'categoria_id' => $categoria->id,
            'nombre' => $nombre, 'estado' => true,
        ]);
        return PresentacionProducto::create([
            'producto_id' => $producto->id,
            'codigo_sku' => $sku,
            'contenido' => 5, 'unidad_medida' => 'L',
            'tipo_envase' => 'Galón', 'tipo_empaque' => 'Paquete',
            'unidades_por_empaque' => $unidades,
            'venta_por_unidad' => true, 'venta_por_empaque' => true,
            'estado' => true,
        ]);
    }

    private function pedido(User $usuario, string $estado = 'confirmado', ?string $fecha = null): Pedido
    {
        $cliente = Cliente::firstOrCreate(['nombre' => 'Cliente prueba'], ['estado' => true]);
        return Pedido::create([
            'cliente_id' => $cliente->id, 'usuario_id' => $usuario->id,
            'fecha_pedido' => now('America/Lima')->toDateString(),
            'fecha_entrega' => $fecha ?? now('America/Lima')->addDay()->toDateString(),
            'condicion_pago' => 'contado', 'estado' => $estado,
            'total' => '0.00', 'cliente_nombre' => 'Cliente prueba',
        ]);
    }

    private function detalle(Pedido $pedido, ?PresentacionProducto $presentacion, string $modalidad, int $cantidad, ?string $sku = null): void
    {
        $pedido->detalles()->create([
            'presentacion_producto_id' => $presentacion?->id,
            'codigo_sku' => $sku ?? $presentacion?->codigo_sku ?? 'HIST-SKU',
            'descripcion_producto' => $presentacion?->producto?->nombre ?? 'Producto histórico',
            'modalidad' => $modalidad,
            'cantidad' => $cantidad,
            'precio_unitario' => '10.00', 'importe' => number_format($cantidad * 10, 2, '.', ''),
        ]);
    }

    public function test_lista_muestra_cuatro_columnas_y_unidades_fisicas_por_empaque(): void
    {
        $usuario = $this->usuario();
        $presentacion = $this->presentacion('LEJ-5L', 4);
        $this->detalle($this->pedido($usuario), $presentacion, 'empaque', 3);

        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'))
            ->assertOk()
            ->assertSee('Presentación y modalidad')
            ->assertSee('Cantidad solicitada')
            ->assertSee('Total de unidades')
            ->assertSee('3 empaques')
            ->assertSee('Empaque')
            ->assertSee('>12</strong>', false);
    }
    public function test_tres_pedidos_suman_diez_empaques_y_cuarenta_unidades(): void
    {
        $usuario = $this->usuario();
        $presentacion = $this->presentacion('LEJ-5L', 4);
        foreach ([3, 5, 2] as $cantidad) {
            $this->detalle($this->pedido($usuario), $presentacion, 'empaque', $cantidad);
        }
        $contenido = $this->actingAs($usuario)
            ->get(route('cumplimiento.lista-preparacion'))->assertOk()->getContent();

        $this->assertStringContainsString('10 empaques', $contenido);
        $this->assertStringContainsString('>40</strong>', $contenido);
        $this->assertSame(1, substr_count($contenido, 'SKU: LEJ-5L'));
    }

    public function test_agrupa_por_sku_y_modalidad_y_excluye_estados_terminados(): void
    {
        $usuario = $this->usuario();
        $uno = $this->presentacion('LEJ-A', 4, 'Lejía');
        $dos = $this->presentacion('LEJ-B', 6, 'Lejía');
        $this->detalle($this->pedido($usuario, 'confirmado'), $uno, 'empaque', 3);
        $this->detalle($this->pedido($usuario, 'en_preparacion'), $uno, 'unidad', 5);
        $this->detalle($this->pedido($usuario, 'en_preparacion'), $dos, 'empaque', 2);
        foreach (['registrado', 'observado', 'cancelado', 'listo_despacho', 'despachado', 'entregado'] as $estado) {
            $excluido = $this->presentacion('EXCL-' . $estado, 4, 'No preparar ' . $estado);
            $this->detalle($this->pedido($usuario, $estado), $excluido, 'unidad', 99);
        }

        $respuesta = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'));
        $respuesta->assertOk()->assertSee('3 empaques')->assertSee('5 unidades')
            ->assertSee('2 empaques')->assertSee('>12</strong>', false);
        $html = $respuesta->getContent();
        $this->assertSame(2, substr_count($html, 'SKU: LEJ-A'));
        $this->assertSame(1, substr_count($html, 'SKU: LEJ-B'));
        foreach (['registrado', 'observado', 'cancelado', 'listo_despacho', 'despachado', 'entregado'] as $estado) {
            $respuesta->assertDontSee('SKU: EXCL-' . $estado);
        }
    }
    public function test_explicacion_visible_y_empaque_historico_sin_multiplicador(): void
    {
        $usuario = $this->usuario();
        $this->detalle($this->pedido($usuario), null, 'empaque', 2, 'SKU-HISTORICO');
        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'))
            ->assertOk()
            ->assertSee('SKU-HISTORICO')
            ->assertSee('2 empaques')
            ->assertSee('No disponible')
            ->assertSee('parcialmente preparadas')
            ->assertSee('catálogo actual');
    }
}
