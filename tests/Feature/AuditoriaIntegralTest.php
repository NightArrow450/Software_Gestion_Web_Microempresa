<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Producto;
use App\Services\RegistrarEnvasado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Tests\UsabilidadEscenario;

class AuditoriaIntegralTest extends TestCase
{
    use RefreshDatabase, UsabilidadEscenario;

    public function test_errores_de_pedido_se_muestran_una_sola_vez_y_tienen_destino_de_foco(): void
    {
        $e = $this->escenarioUnidades();
        $html = $this->actingAs($e['usuario'])->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['cliente_id' => 'ERROR_FICTICIO_UNICO']))])
            ->get(route('pedidos.create'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'ERROR_FICTICIO_UNICO'));
    }

    public function test_mensaje_del_editor_admite_foco_por_programa(): void
    {
        $e = $this->escenarioUnidades();
        $html = $this->actingAs($e['usuario'])->get(route('pedidos.create'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $mensaje = (new \DOMXPath($dom))->query('//*[@data-mensaje]')->item(0);
        $this->assertSame('-1', $mensaje->getAttribute('tabindex'));
    }

    public function test_disponibilidad_encuentra_un_pedido_antiguo_entre_mas_de_20_sin_tildes(): void
    {
        $e = $this->escenarioUnidades();
        $pedido = $this->pedidoUnico($e, 4);
        $pedido->update(['estado' => 'confirmado', 'cliente_nombre' => 'JOSÉ NÚÑEZ ficticio']);
        for ($i = 0; $i < 22; $i++) {
            $otro = $this->pedidoUnico($e, 1);
            $otro->update(['estado' => 'confirmado']);
        }
        $this->actingAs($e['usuario']);
        $this->get(route('inventario.index'))->assertOk()->assertViewHas('pedidosDisponibles', fn ($p) => $p->total() === 23 && ! $p->contains('id', $pedido->id));
        $this->get(route('inventario.index', ['pedido_buscar' => 'jose nunez']))->assertOk()
            ->assertViewHas('pedidosDisponibles', fn ($p) => $p->total() === 1 && $p->first()->id === $pedido->id);
        $this->get(route('inventario.index', ['pedido_buscar' => $pedido->numero]))->assertOk()->assertViewHas('pedidosDisponibles', fn ($p) => $p->contains('id', $pedido->id));
        $this->get(route('inventario.index', ['pedido_buscar' => "' OR 1=1 --"]))->assertOk()->assertViewHas('pedidosDisponibles', fn ($p) => $p->total() === 0);
        $this->getJson(route('inventario.index', ['pedido_buscar' => str_repeat('x', 101)]))->assertUnprocessable();
        $this->assertDatabaseCount('pedidos', 23);
    }

    public static function imagenes(): array
    {
        return [['crear'], ['reemplazar'], ['quitar']];
    }

    #[DataProvider('imagenes')]
    public function test_un_fallo_de_base_no_borra_la_imagen_anterior_ni_deja_una_nueva_huerfana(string $accion): void
    {
        Storage::fake('public');
        $e = $this->escenarioEnvasado();
        $e['usuario']->role->update(['nombre' => 'Administrador']);
        foreach (['productos.crear', 'productos.editar'] as $permiso) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $permiso])->id);
        }
        $e['usuario']->unsetRelation('role');
        $this->actingAs($e['usuario']);
        $producto = $e['presentacion']->producto;
        $producto->update(['imagen_referencia' => 'productos/anterior.png']);
        Storage::disk('public')->put('productos/anterior.png', 'Imagen original ficticia');
        $datos = ['codigo' => $accion === 'crear' ? 'AUD-NUEVO' : $producto->codigo, 'nombre' => 'Producto ficticio', 'categoria_id' => $producto->categoria_id,
            'estado' => 1, 'prefijo_sku' => 'AUD', 'presentaciones' => [['contenido' => 1, 'unidad_medida' => 'L', 'venta_por_unidad' => 1,
                'venta_por_empaque' => 0, 'precio_unitario' => 2, 'estado' => 1]]];
        if ($accion === 'quitar') {
            $datos['eliminar_imagen'] = 1;
        } else {
            $datos['imagen_referencia'] = UploadedFile::fake()->createWithContent('nueva.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWQAAAABJRU5ErkJggg=='));
        }
        $evento = $accion === 'crear' ? 'creating' : 'updating';
        Event::listen('eloquent.'.$evento.': '.Producto::class, static function (): void {
            throw new RuntimeException('Fallo de base ficticio');
        });
        $this->withoutExceptionHandling();
        try {
            $accion === 'crear' ? $this->post(route('productos.store'), $datos) : $this->put(route('productos.update', $producto), $datos);
            $this->fail('Debe propagarse el fallo de base, sin perder el archivo anterior.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fallo de base ficticio', $error->getMessage());
        }
        $this->assertSame('productos/anterior.png', $producto->fresh()->imagen_referencia);
        $this->assertSame(['productos/anterior.png'], Storage::disk('public')->allFiles('productos'));
        $this->assertDatabaseCount('productos', 1);
    }

    public function test_envasar_no_supera_el_limite_fisico_del_inventario_en_ningun_motor(): void
    {
        $e = $this->escenarioEnvasado();
        config(['envasado.control_materiales_exacto' => false]);
        $e['inventario']->update(['cantidad_actual' => 4294967294]);
        $servicio = app(RegistrarEnvasado::class);
        $this->assertSame(1, $servicio->previsualizar(['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 1])['cantidad']);
        try {
            $servicio->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 2], $e['usuario']);
            $this->fail('Debe rechazarse antes de crear envasados o movimientos.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('cantidad', $error->errors());
        }
        $this->assertSame(4294967294, (int) $e['inventario']->fresh()->cantidad_actual);
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
