<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidacionCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function autenticar(): void
    {
        $rol = Role::create(['nombre' => 'Administrador']);
        foreach (['productos.crear', 'productos.editar', 'inventario.ver', 'inventario.ajustar'] as $nombre) {
            $rol->permisos()->attach(Permiso::firstOrCreate(['nombre' => $nombre])->id);
        }
        $this->actingAs(User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'catalogo@local.test', 'password' => 'Prueba12345', 'role_id' => $rol->id, 'status' => true]));
    }

    private function producto(): array
    {
        $this->autenticar();

        return [
            'codigo' => 'PRD_001-A', 'nombre' => 'Detergente', 'categoria_id' => Categoria::create(['nombre' => 'Limpieza', 'estado' => true])->id, 'estado' => 1, 'prefijo_sku' => 'DET',
            'presentaciones' => [['contenido' => '1', 'unidad_medida' => 'L', 'tipo_empaque' => 'Caja', 'unidades_por_empaque' => 12, 'venta_por_unidad' => 1, 'venta_por_empaque' => 1, 'precio_unitario' => '2.50', 'estado' => 1]],
        ];
    }

    public function test_insumo_unico_por_categoria_sin_distinguir_mayusculas_unicode(): void
    {
        $this->autenticar();
        $datos = ['codigo' => 'qui_01-a', 'nombre' => 'Ácido cítrico', 'categoria' => 'Químicos', 'unidad_medida' => 'kilogramo', 'cantidad_actual' => 1];

        $this->post(route('inventario.insumos.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('insumos', ['codigo' => 'QUI_01-A', 'nombre' => 'Ácido cítrico']);
        $this->post(route('inventario.insumos.store'), array_replace($datos, ['codigo' => 'QUI-02', 'nombre' => 'ÁCIDO CÍTRICO']))->assertSessionHasErrors('nombre');
        $this->post(route('inventario.insumos.store'), array_replace($datos, ['codigo' => 'OTR-01', 'categoria' => 'Otros']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('insumos', 2);
    }

    public static function codigosInvalidos(): array
    {
        return [['INS 01'], ['INS/01'], ['INS@01'], ['ÁCIDO']];
    }

    #[DataProvider('codigosInvalidos')]
    public function test_rechaza_codigos_de_insumo_invalidos(string $codigo): void
    {
        $this->autenticar();
        $this->post(route('inventario.insumos.store'), ['codigo' => $codigo, 'nombre' => 'Envase', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 0])->assertSessionHasErrors('codigo');
        $this->assertDatabaseCount('insumos', 0);
    }

    public static function productosInvalidos(): array
    {
        return [
            'código con espacio' => ['codigo', 'PRD 001'],
            'código minúsculo' => ['codigo', 'prd001'],
            'precio cero' => ['presentaciones.0.precio_unitario', '0'],
            'precio negativo' => ['presentaciones.0.precio_unitario', '-1'],
            'precio supera tope' => ['presentaciones.0.precio_unitario', '1000000'],
            'fracción de céntimo' => ['presentaciones.0.precio_unitario', '1.001'],
            'contenido cero' => ['presentaciones.0.contenido', '0'],
            'contenido negativo' => ['presentaciones.0.contenido', '-1'],
            'contenido redondea a cero' => ['presentaciones.0.contenido', '0.001'],
            'contenido desborda columna' => ['presentaciones.0.contenido', '100000000'],
            'empaque de uno' => ['presentaciones.0.unidades_por_empaque', 1],
            'empaque muy grande' => ['presentaciones.0.unidades_por_empaque', 1001],
            'empaque fraccionario' => ['presentaciones.0.unidades_por_empaque', '2.5'],
            'empaque sin unidades' => ['presentaciones.0.unidades_por_empaque', null],
            'empaque sin precio' => ['presentaciones.0.precio_unitario', null],
        ];
    }

    #[DataProvider('productosInvalidos')]
    public function test_rechaza_producto_invalido_sin_persistir(string $campo, mixed $valor): void
    {
        $datos = $this->producto();
        data_set($datos, $campo, $valor);
        $this->post(route('productos.store'), $datos)->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('presentaciones_producto', 0);
    }

    public static function limitesValidos(): array
    {
        return [
            'mínimo empaque' => [2, '0.01', '0.02'],
            'máximo empaque' => [1000, '2.50', '2500.00'],
            'precio empaque cercano al tope' => [3, '333333.33', '999999.99'],
        ];
    }

    public function test_edicion_invalida_conserva_producto_presentacion_y_precio(): void
    {
        $datos = $this->producto();
        $this->post(route('productos.store'), $datos)->assertSessionHasNoErrors();
        $producto = Producto::firstOrFail();
        $presentacion = $producto->presentaciones()->firstOrFail();
        $datos['nombre'] = 'Nombre que no debe guardarse';
        $datos['presentaciones'][0]['ids'] = ['__none__' => $presentacion->id];
        $datos['presentaciones'][0]['precio_unitario'] = 0;

        $this->put(route('productos.update', $producto), $datos)->assertSessionHasErrors('presentaciones.0.precio_unitario');
        $this->assertSame('Detergente', $producto->fresh()->nombre);
        $this->assertSame('2.50', $presentacion->fresh()->precio_unitario);
        $this->assertSame('30.00', $presentacion->fresh()->precio_empaque);
        $this->assertDatabaseCount('presentaciones_producto', 1);
    }

    #[DataProvider('limitesValidos')]
    public function test_acepta_limites_y_conserva_precio_automatico(int $unidades, string $precio, string $total): void
    {
        $datos = $this->producto();
        $datos['presentaciones'][0]['unidades_por_empaque'] = $unidades;
        $datos['presentaciones'][0]['precio_unitario'] = $precio;
        $datos['presentaciones'][0]['precio_empaque'] = '0.01';
        $this->post(route('productos.store'), $datos)->assertSessionHasNoErrors();
        $presentacion = Producto::firstOrFail()->presentaciones()->firstOrFail();
        $this->assertSame($total, $presentacion->precio_empaque);
        $this->assertSame($unidades, $presentacion->unidades_por_empaque);
    }

    public function test_rechaza_total_del_empaque_superior_al_tope(): void
    {
        $datos = $this->producto();
        $datos['presentaciones'][0]['precio_unitario'] = '500000';
        $datos['presentaciones'][0]['unidades_por_empaque'] = 2;
        $this->post(route('productos.store'), $datos)->assertSessionHasErrors('presentaciones.0.unidades_por_empaque');
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_venta_solo_por_unidad_admite_tope_y_precio_pendiente(): void
    {
        $datos = $this->producto();
        $datos['presentaciones'][0]['venta_por_empaque'] = 0;
        $datos['presentaciones'][0]['precio_unitario'] = '999999.99';
        $this->post(route('productos.store'), $datos)->assertSessionHasNoErrors();
        $producto = Producto::firstOrFail();
        $presentacion = $producto->presentaciones()->firstOrFail();
        $this->assertSame('999999.99', $presentacion->precio_unitario);
        $this->assertNull($presentacion->precio_empaque);

        $datos['presentaciones'][0]['ids'] = ['__none__' => $presentacion->id];
        $datos['presentaciones'][0]['precio_unitario'] = null;
        $this->put(route('productos.update', $producto), $datos)->assertSessionHasNoErrors();
        $this->assertNull($presentacion->fresh()->precio_unitario);
    }
}
