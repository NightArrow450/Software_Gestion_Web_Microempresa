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
}
