<?php

namespace Tests\Feature;

use App\Http\Middleware\PermisoMiddleware;
use App\Models\Inventario;
use App\Models\Permiso;
use App\Models\Producto;
use App\Services\RegistrarEnvasado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Tests\UsabilidadEscenario;

class AuditoriaIntegralTest extends TestCase
{
    use RefreshDatabase, UsabilidadEscenario;

    public function test_errores_de_pedido_se_muestran_una_sola_vez_y_tienen_destino_de_foco(): void
    {
        $e = $this->escenarioUnidades();
        $respuesta = $this->actingAs($e['usuario'])->get(route('pedidos.create'))->assertOk();
        $html = $respuesta->original->with('errors', (new ViewErrorBag)->put('default', new MessageBag(['cliente_id' => 'ERROR_FICTICIO_UNICO'])))->render();
        $this->assertSame(1, substr_count($html, 'ERROR_FICTICIO_UNICO'));
    }

    public function test_mensaje_del_editor_admite_foco_por_programa(): void
    {
        $e = $this->escenarioUnidades();
        $html = $this->actingAs($e['usuario'])->get(route('pedidos.create'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $mensaje = (new \DOMXPath($dom))->query('//*[@data-mensaje]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $mensaje);
        $this->assertSame('-1', $mensaje->getAttribute('tabindex'));
    }

    public function test_disponibilidad_encuentra_un_pedido_antiguo_entre_mas_de_20_sin_tildes(): void
    {
        $this->comprobarBusquedaDisponibilidad();
    }

    public function test_permisos_se_recargan_una_vez_por_solicitud_y_no_sobreviven_a_la_siguiente(): void
    {
        $e = $this->escenarioUnidades();
        $usuario = $e['usuario'];
        $permiso = 'envasado.ver';
        $crear = static function () use ($usuario): Request {
            $r = Request::create('/envasados');
            $r->setUserResolver(fn () => $usuario);

            return $r;
        };
        $middleware = new PermisoMiddleware;
        $continuar = fn () => response('Lectura autorizada');
        $request = $crear();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $usuario->load('role.permisos');
        $usuario->load('role.permisos');
        $antes = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->assertSame(200, $middleware->handle($request, $continuar, $permiso)->getStatusCode());
        $this->assertSame(200, $middleware->handle($request, $continuar, $permiso)->getStatusCode());
        $despues = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(4, $antes);
        $this->assertSame(2, $despues);
        $usuario->role->permisos()->detach(Permiso::where('nombre', $permiso)->value('id'));
        try {
            $middleware->handle($crear(), $continuar, $permiso);
            $this->fail('Una solicitud nueva debe observar la revocación del permiso.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public static function imagenes(): array
    {
        return [['crear', true], ['reemplazar', true], ['quitar', true], ['crear', false], ['reemplazar', false], ['quitar', false], ['reemplazar', true, true]];
    }

    #[DataProvider('imagenes')]
    public function test_un_fallo_de_base_no_borra_la_imagen_anterior_ni_deja_una_nueva_huerfana(string $accion, bool $fallo, bool $posterior = false): void
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
        if (! $fallo) {
            $respuesta = $accion === 'crear' ? $this->post(route('productos.store'), $datos) : $this->put(route('productos.update', $producto), $datos);
            $respuesta->assertSessionHasNoErrors();
            $actual = $accion === 'crear' ? Producto::where('codigo', 'AUD-NUEVO')->sole() : $producto->fresh();
            if ($accion === 'quitar') {
                $this->assertNull($actual->imagen_referencia);
                $this->assertSame([], Storage::disk('public')->allFiles('productos'));
            } else {
                $this->assertNotSame('productos/anterior.png', $actual->imagen_referencia);
                Storage::disk('public')->assertExists($actual->imagen_referencia);
                $this->assertCount($accion === 'crear' ? 2 : 1, Storage::disk('public')->allFiles('productos'));
            }

            return;
        }
        if ($posterior) {
            app('router')->getRoutes()->getByName('productos.update')->middleware(FalloPosteriorImagen::class);
        } else {
            $evento = $accion === 'crear' ? 'creating' : 'updating';
            Event::listen('eloquent.'.$evento.': '.Producto::class, static function (): void {
                throw new RuntimeException('Fallo de base ficticio');
            });
        }
        $this->withoutExceptionHandling();
        try {
            $accion === 'crear' ? $this->post(route('productos.store'), $datos) : $this->put(route('productos.update', $producto), $datos);
            $this->fail('Debe propagarse el fallo de base, sin perder el archivo anterior.');
        } catch (RuntimeException $error) {
            $this->assertSame($posterior ? 'Fallo posterior ficticio' : 'Fallo de base ficticio', $error->getMessage());
        }
        $this->assertSame('productos/anterior.png', $producto->fresh()->imagen_referencia);
        $this->assertSame(['productos/anterior.png'], Storage::disk('public')->allFiles('productos'));
        $this->assertDatabaseCount('productos', 1);
    }

    public function test_envasar_no_supera_el_limite_fisico_del_inventario_en_ningun_motor(): void
    {
        $e = $this->escenarioEnvasado();
        config(['envasado.control_materiales_exacto' => false]);
        $inventario = Inventario::where('presentacion_producto_id', $e['presentacion']->id)->sole();
        $inventario->update(['cantidad_actual' => 4294967294]);
        $servicio = app(RegistrarEnvasado::class);
        $this->assertSame(1, $servicio->previsualizar(['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 1])['cantidad']);
        try {
            $servicio->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 2], $e['usuario']);
            $this->fail('Debe rechazarse antes de crear envasados o movimientos.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('cantidad', $error->errors());
        }
        $this->assertSame(4294967294, (int) $inventario->fresh()->cantidad_actual);
        $this->assertDatabaseCount('envasados', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}

/** Falla después del controlador, antes de confirmar las transacciones de los middleware reales. */
class FalloPosteriorImagen
{
    public function handle(Request $request, \Closure $next): Response
    {
        $next($request);
        throw new RuntimeException('Fallo posterior ficticio');
    }
}
