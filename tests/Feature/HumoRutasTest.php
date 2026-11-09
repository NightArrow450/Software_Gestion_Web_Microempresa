<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RegistrarEnvasado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\EnvasadoEscenario;
use Tests\RolesEscenario;
use Tests\TestCase;

class HumoRutasTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase, RolesEscenario;

    public static function sesiones(): array
    {
        return [['administrador', true], ['visitante', false]];
    }

    #[DataProvider('sesiones')]
    public function test_get_de_aplicacion_con_parametros_validos_respeta_respuesta_y_acceso(string $sesion, bool $autenticado): void
    {
        $this->instalarRolesDePrueba();
        $e = $this->escenarioEnvasado(100, 10);
        $admin = User::factory()->create(['role_id' => $this->rolDePrueba('Administrador')->id]);
        $pedido = $this->pedidoEnvasado($e, 'registrado');
        $otro = $this->pedidoEnvasado($e, 'registrado');
        $envasado = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id, 'version_configuracion' => 0, 'cantidad' => 1], $admin);
        $parametros = ['pedido' => $pedido->id, 'envasado' => $envasado->id, 'presentacion' => $e['presentacion']->id, 'producto' => $e['presentacion']->producto_id, 'categoria' => $e['presentacion']->producto->categoria_id, 'cliente' => $pedido->cliente_id, 'user' => $admin->id, 'role' => $admin->role_id, 'permiso' => $this->rolDePrueba('Administrador')->permisos()->firstOrFail()->id];
        if ($autenticado) {
            $this->actingAs($admin);
        }
        $antes = DB::table('movimientos_inventario')->orderBy('id')->get()->toJson();
        $total = 0;
        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            if (! in_array('GET', $ruta->methods(), true) || str_starts_with($ruta->uri(), 'storage/')) {
                continue;
            }
            $uri = preg_replace_callback('/{(\w+)}/', function (array $m) use ($parametros): string {
                $this->assertArrayHasKey($m[1], $parametros, 'Falta fixture para parámetro de ruta');

                return (string) $parametros[$m[1]];
            }, $ruta->uri());
            $url = '/'.ltrim($uri, '/');
            if ($ruta->getName() === 'pedidos.imprimir-dos') {
                $url .= '?pedido_1='.$pedido->id.'&pedido_2='.$otro->id;
            }
            if ($ruta->getName() === 'pedidos.imprimir-por-fecha') {
                $url .= '?fecha_pedido='.today()->toDateString();
            }
            $compatibilidad = in_array($ruta->uri(), ['/', 'envasados/previsualizar', 'envasados/{envasado}/correcciones/previsualizar'], true);
            $protegida = in_array('auth', $ruta->gatherMiddleware(), true);
            $esperado = ($compatibilidad || ($autenticado && $ruta->getName() === 'login') || (! $autenticado && $protegida)) ? 302 : 200;
            $respuesta = $this->get($url);
            $this->assertSame($esperado, $respuesta->getStatusCode(), $sesion.' GET '.$url);
            if (! $autenticado && $protegida) {
                $respuesta->assertRedirect(route('login'));
            }
            if ($autenticado && $ruta->getName() === 'indicadores.exportar') {
                $this->assertStringStartsWith("\xEF\xBB\xBF", $respuesta->streamedContent());
            }
            $total++;
        }
        $this->assertSame($antes, DB::table('movimientos_inventario')->orderBy('id')->get()->toJson());
        fwrite(STDERR, 'HUMO_RUTAS '.$sesion.' GET='.$total.PHP_EOL);
    }
}
