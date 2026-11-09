<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ConfiguracionEnvasado;
use App\Models\Insumo;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\EnvasadoEscenario;
use Tests\RolesEscenario;
use Tests\TestCase;

class FlujosEnvasadoCompletoTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase, RolesEscenario;

    public static function flujos(): array
    {
        return [['normal'], ['vinculado'], ['anticipado']];
    }

    private function verificarInventario(): void
    {
        $this->assertSame(0, Artisan::call('inventario:verificar', ['--json' => true]));
        $this->assertTrue(json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['correcto']);
    }

    #[DataProvider('flujos')]
    public function test_flujo_completo_conserva_insumos_historia_y_stock_previo(string $modo): void
    {
        $this->instalarRolesDePrueba();
        $e = $this->escenarioEnvasado(0, 7);
        $admin = User::factory()->create(['role_id' => $this->rolDePrueba('Administrador')->id]);
        $this->actingAs($admin);
        $materiales = [$e['insumo']];
        foreach (['Etiqueta', 'Contratapa'] as $nombre) {
            $materiales[] = Insumo::create(['codigo' => 'PRUEBA-'.$nombre, 'nombre' => $nombre.' ficticia', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 0, 'estado' => true]);
        }
        $configurar = [];
        foreach ($materiales as $i => $insumo) {
            $configurar[] = ['insumo_id' => $insumo->id, 'funcion' => ['envase', 'etiqueta', 'contratapa'][$i], 'base_consumo' => 'unidad', 'cantidad_por_unidad' => '1.000'];
        }
        $this->post(route('envasado.materiales.store', $e['presentacion']), ['nombre' => 'Configuración ficticia del flujo final', 'materiales' => $configurar, 'estado' => 1, 'version' => 0])->assertSessionHasNoErrors()->assertRedirect();
        $configuracion = ConfiguracionEnvasado::where('nombre', 'Configuración ficticia del flujo final')->firstOrFail();
        foreach ($materiales as $insumo) {
            $this->post(route('inventario.movimientos.store'), ['insumo_id' => $insumo->id, 'tipo' => 'entrada', 'cantidad' => 100, 'motivo' => 'Ingreso ficticio para prueba funcional final.'])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->verificarInventario();
        $envasar = function (?int $pedidoId) use ($e, $configuracion): void {
            foreach ([2, 3] as $cantidad) {
                $this->post(route('envasado.store'), ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $configuracion->id, 'version_configuracion' => 0, 'cantidad' => $cantidad, 'pedido_id' => $pedidoId])->assertSessionHasNoErrors()->assertRedirect();
                $this->verificarInventario();
            }
        };
        if ($modo === 'anticipado') {
            $this->assertDatabaseCount('pedidos', 0);
            $envasar(null);
        }
        $cliente = Cliente::create(['nombre' => 'Cliente ficticio del flujo final', 'estado' => true]);
        $this->post(route('pedidos.store'), ['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $e['presentacion']->id, 'modalidad' => 'unidad', 'cantidad' => 5]]])->assertSessionHasNoErrors()->assertRedirect();
        $pedido = Pedido::latest('id')->firstOrFail();
        $this->post(route('pedidos.procesar', $pedido), ['accion' => 'confirmar'])->assertSessionHasNoErrors()->assertRedirect();
        if ($modo === 'vinculado') {
            $envasar($pedido->id);
        }
        $this->assertSame('confirmado', $pedido->fresh()->estado);
        $insumosAntes = DB::table('insumos')->orderBy('id')->pluck('cantidad_actual')->all();
        foreach (['iniciar-preparacion' => 'en_preparacion', 'finalizar-preparacion' => 'listo_despacho', 'despachar' => 'despachado', 'entregar' => 'entregado'] as $accion => $estado) {
            if ($accion === 'finalizar-preparacion') {
                $this->post(route('pedidos.unidades.preparar', $pedido), [
                    'version_pedido' => $pedido->fresh()->version,
                    'pedido_detalle_id' => $pedido->detalles()->sole()->id,
                    'cantidad' => 5,
                    'motivo' => 'Se verificaron físicamente cinco unidades preparadas.',
                ])->assertSessionHasNoErrors()->assertRedirect();
            }
            if ($accion === 'despachar') {
                $this->post(route('pedidos.unidades.despachar', $pedido), [
                    'version_pedido' => $pedido->fresh()->version,
                    'operacion' => 'despacho_flujo_completo_0001',
                    'motivo' => 'Despacho de las cinco unidades realmente preparadas.',
                    'detalles' => [['id' => $pedido->detalles()->sole()->id, 'cantidad' => 5]],
                ])->assertSessionHasNoErrors()->assertRedirect();
            } else {
                $this->post(route('cumplimiento.'.$accion, $pedido))->assertSessionHasNoErrors()->assertRedirect();
            }
            $this->assertSame($estado, $pedido->fresh()->estado);
            $this->verificarInventario();
        }
        $this->assertSame($insumosAntes, DB::table('insumos')->orderBy('id')->pluck('cantidad_actual')->all());
        $this->assertSame($modo === 'normal' ? 2 : 7, (int) DB::table('inventarios')->where('presentacion_producto_id', $e['presentacion']->id)->value('cantidad_actual'));
        $this->assertDatabaseCount('envasados', $modo === 'normal' ? 0 : 2);
        foreach ($materiales as $insumo) {
            $this->assertSame($modo === 'normal' ? 100.0 : 95.0, (float) $insumo->fresh()->cantidad_actual);
        }
        $despachos = DB::table('pedido_despachos')->where('pedido_id', $pedido->id)->pluck('id');
        $this->assertSame(1, DB::table('movimientos_inventario')->whereIn('despacho_unidades_id', $despachos)->where('origen_tipo', 'despacho_unidades')->count());
        $this->assertSame(['registrado', 'confirmado', 'en_preparacion', 'listo_despacho', 'despachado', 'entregado'], $pedido->historialEstados()->orderBy('id')->pluck('estado_nuevo')->all());
        foreach (['seguimiento.show', 'seguimiento.trazabilidad'] as $ruta) {
            $this->get(route($ruta, $pedido))->assertOk()->assertSee($pedido->numero);
        }
        $this->get(route('seguimiento.historial'))->assertOk()->assertSee($pedido->numero);
    }
}
