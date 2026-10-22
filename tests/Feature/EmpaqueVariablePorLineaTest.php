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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmpaqueVariablePorLineaTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(?array $permisos = null): array
    {
        $this->freezeTime();
        $rol = Role::create(['nombre' => 'Operaciones Comerciales']);
        foreach ($permisos ?? ['pedidos.crear', 'pedidos.editar', 'pedidos.ver', 'inventario.ver', 'cumplimiento.despachar'] as $nombre) {
            $rol->permisos()->attach(Permiso::firstOrCreate(['nombre' => $nombre])->id);
        }
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'empaques@example.com', 'password' => 'Prueba12345', 'role_id' => $rol->id, 'status' => true]);
        $this->actingAs($usuario);
        $cliente = Cliente::create(['nombre' => 'Cliente Prueba', 'estado' => true]);
        $categoria = Categoria::create(['nombre' => 'Limpieza', 'estado' => true]);
        $producto = Producto::create(['codigo' => 'PRO-1', 'nombre' => 'Limpiador', 'categoria_id' => $categoria->id, 'estado' => true]);
        $presentacion = PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => 'PRO-1-L', 'contenido' => 1, 'unidad_medida' => 'L', 'tipo_empaque' => 'Caja', 'unidades_por_empaque' => 12, 'venta_por_unidad' => true, 'venta_por_empaque' => true, 'precio_unitario' => '2.50', 'estado' => true]);
        $datos = ['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $presentacion->id, 'modalidad' => 'empaque', 'cantidad' => 5, 'unidades_por_empaque' => 15]]];

        return [$datos, $presentacion, $usuario];
    }

    public function test_migracion_copia_tamano_sin_alterar_los_datos_existentes(): void
    {
        [$datos, $presentacion, $usuario] = $this->escenario();
        $pedido = Pedido::create(['cliente_id' => $datos['cliente_id'], 'usuario_id' => $usuario->id, 'fecha_pedido' => today(), 'fecha_entrega' => today(), 'condicion_pago' => 'contado', 'estado' => 'registrado', 'total' => '199.99', 'cliente_nombre' => 'Cliente Prueba']);
        $base = ['codigo_sku' => $presentacion->codigo_sku, 'descripcion_producto' => 'Descripción histórica', 'cantidad' => 5, 'precio_unitario' => '39.99', 'importe' => '199.95'];
        foreach (['empaque', 'unidad'] as $modalidad) {
            $pedido->detalles()->create($base + ['presentacion_producto_id' => $presentacion->id, 'modalidad' => $modalidad]);
        }
        $pedido->detalles()->create($base + ['modalidad' => 'empaque']);
        $migracion = require database_path('migrations/2026_10_22_023227_add_unidades_por_empaque_to_pedido_detalles_table.php');
        $migracion->down();
        $antes = DB::table('pedido_detalles')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
        $migracion->up();
        $despues = DB::table('pedido_detalles')->orderBy('id')->get();

        $this->assertSame([12, null, null], $despues->pluck('unidades_por_empaque')->all());
        $this->assertSame($antes, $despues->map(function ($fila): array {
            $datos = (array) $fila;
            unset($datos['unidades_por_empaque']);

            return $datos;
        })->all());
        $this->assertSame('199.99', $pedido->fresh()->total);
    }

    public function test_cinco_cajas_de_quince_recalculan_precio_e_importe_en_servidor(): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][0]['precio_unitario'] = '0.01';
        $datos['detalles'][0]['importe'] = '0.01';
        $datos['total'] = '0.01';

        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 15, 'precio_unitario' => '37.50', 'cantidad' => 5, 'importe' => '187.50']);
        $this->assertDatabaseHas('pedidos', ['total' => '187.50']);
    }

    public function test_omitir_tamano_copia_catalogo_y_editar_guarda_nuevo_tamano(): void
    {
        [$datos] = $this->escenario();
        unset($datos['detalles'][0]['unidades_por_empaque']);
        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 12, 'precio_unitario' => '30.00']);

        $datos['detalles'][0]['unidades_por_empaque'] = 15;
        $this->put(route('pedidos.update', $pedido), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 15, 'precio_unitario' => '37.50']);
        $this->assertSame('187.50', $pedido->fresh()->total);
    }

    public static function tamanosInvalidos(): array
    {
        return ['uno' => [1], 'mil uno' => [1001], 'decimal' => ['2.5'], 'texto' => ['doce'], 'arreglo' => [[15]]];
    }

    #[DataProvider('tamanosInvalidos')]
    public function test_tamano_invalido_rechaza_alta_y_edicion_sin_cambiar_datos(mixed $tamano): void
    {
        [$datos] = $this->escenario();
        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $pedido = Pedido::firstOrFail();
        $datos['detalles'][0]['unidades_por_empaque'] = $tamano;

        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors('detalles.0.unidades_por_empaque');
        $this->put(route('pedidos.update', $pedido), $datos)->assertSessionHasErrors('detalles.0.unidades_por_empaque');
        $this->assertDatabaseCount('pedidos', 1);
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => 15, 'importe' => '187.50']);
    }

    public static function tamanosValidos(): array
    {
        return ['dos' => [2, '5.00'], 'mil' => [1000, '2500.00']];
    }

    #[DataProvider('tamanosValidos')]
    public function test_acepta_tamanos_limite(int $tamano, string $precio): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][0]['unidades_por_empaque'] = $tamano;
        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => $tamano, 'precio_unitario' => $precio]);
    }

    public function test_unidad_ignora_el_tamano_incluso_si_es_invalido(): void
    {
        [$datos] = $this->escenario();
        $datos['detalles'][0]['modalidad'] = 'unidad';
        $datos['detalles'][0]['unidades_por_empaque'] = ['manipulado'];
        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['unidades_por_empaque' => null, 'precio_unitario' => '2.50', 'importe' => '12.50']);
    }

    public function test_sin_precio_base_rechaza_empaque_con_mensaje_claro(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $presentacion->update(['precio_unitario' => null, 'precio_empaque' => '30.00']);
        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors([
            'detalles.0.modalidad' => 'Registra el precio unitario de la presentación para calcular el precio del empaque.',
        ]);
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_importe_de_linea_respeta_tope_inclusivo(): void
    {
        [$datos, $presentacion] = $this->escenario();
        $presentacion->update(['precio_unitario' => '333333.33']);
        $datos['detalles'][0]['unidades_por_empaque'] = 3;
        $datos['detalles'][0]['cantidad'] = 1;
        $this->post(route('pedidos.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_detalles', ['importe' => '999999.99']);

        $datos['detalles'][0]['cantidad'] = 2;
        $this->post(route('pedidos.store'), $datos)->assertSessionHasErrors('detalles.0.cantidad');
        $this->assertDatabaseCount('pedidos', 1);
    }
}
