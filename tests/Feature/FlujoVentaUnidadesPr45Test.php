<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Permiso;
use App\Services\AprobarRetornoPedido;
use App\Services\AtenderPedidoUnidades;
use App\Services\ControlEntregaPedido;
use App\Services\FilasIndicadores;
use App\Services\GestionarPedidoUnidades;
use App\Services\RegistrarResultadoEntrega;
use App\Services\RegistrarRetornoPedido;
use App\Services\TokenOperacion;
use App\Services\TransicionesPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class FlujoVentaUnidadesPr45Test extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    public static function resultados(): array
    {
        return ['aceptación completa' => [32, 0], 'rechazo parcial' => [30, 2], 'rechazo completo' => [0, 32], 'flujo completo sin estructuras retiradas' => [30, 2, true], 'flujo completo tras retiro definitivo' => [30, 2, true, true]];
    }

    #[DataProvider('resultados')]
    public function test_54_a_32_con_produccion_real_edicion_auditada_y_retorno_solo_aprobado(int $aceptadas, int $rechazadas, bool $retirarEstructuras = false, bool $definitivo = false): void
    {
        $this->freezeTime();
        if ($retirarEstructuras) {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO='.($definitivo ? 'RETIRAR_ESTRUCTURAS_SURTIDOS_OBSOLETAS' : 'RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS'));
            try {
                (require database_path('migrations/retiro_controlado/'.($definitivo ? '2026_10_31_174658_retirar_definitivamente_surtidos_obsoletos.php' : '2026_10_30_003639_retirar_estructuras_surtidos_vacias.php')))->up();
            } finally {
                putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
            }
            DB::enableQueryLog();
        }
        $e = $this->escenarioEnvasado(100, 0);
        foreach (['pedidos.crear', 'pedidos.editar', 'cumplimiento.preparar', 'cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar', 'indicadores.ver', 'indicadores.economicos.ver', 'indicadores.exportar', 'seguimiento.ver'] as $permiso) {
            $e['usuario']->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => $permiso], ['descripcion' => $permiso])->id);
        }
        $e['usuario']->unsetRelation('role');
        $this->actingAs($e['usuario']);
        $cuentas = [];
        foreach (['users', 'roles', 'permisos', 'rol_permiso'] as $tabla) {
            $cuentas[$tabla] = DB::table($tabla)->get()->toArray();
        }
        $cliente = Cliente::create(['nombre' => 'Cliente ficticio del caso 54 a 32', 'estado' => true]);
        $entrada = ['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado',
            'detalles' => [['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 54]], 'agrupaciones' => []];
        $pedido = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);
        app(TransicionesPedido::class)->registrar($pedido, 'registrado', 'confirmado', $e['usuario']->id, [], 'Confirmación sin consultar disponibilidad.');
        $pedido->refresh();
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 0]);
        $confirmacion = ['presentacion_producto_id' => $e['presentacion']->id, 'configuracion_envasado_id' => $e['configuracion']->id,
            'version_configuracion' => $e['configuracion']->version, 'cantidad' => 32, 'pedido_id' => $pedido->id, '_token_operacion' => TokenOperacion::emitir($e['usuario']->id)];
        $this->post(route('envasado.store'), $confirmacion)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('envasado.store'), $confirmacion)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('envasados', 1);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertSame('68.000', $e['insumo']->fresh()->cantidad_actual);
        $detalle = $pedido->detalles()->sole();
        app(AtenderPedidoUnidades::class)->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id,
            'cantidad' => 32, 'incidencia' => 'insumos_quimicos', 'motivo' => 'Se agotó el insumo químico; no se pudieron envasar las otras 22 unidades.'], $e['usuario']);
        $pedido->refresh();
        $movimientos = DB::table('movimientos_inventario')->get()->toArray();
        $entrada['detalles'][0] += ['id' => $detalle->id];
        $entrada['detalles'][0]['cantidad'] = 32;
        $entrada['_version'] = $pedido->version;
        $entrada['motivo'] = 'Se reduce de 54 a 32 por falta de insumo químico comunicada por Preparación.';
        $pedido = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario'], $pedido);
        $this->assertSame('64.00', $pedido->total);
        $versiones = DB::table('pedido_versiones')->orderBy('numero')->get();
        $this->assertSame(54, json_decode($versiones->first()->datos, true)['lineas'][0]['cantidad']);
        $this->assertSame(32, json_decode($versiones->last()->datos, true)['lineas'][0]['cantidad']);
        $this->assertSame($e['usuario']->id, (int) $versiones->last()->usuario_id);
        $this->assertSame($entrada['motivo'], $versiones->last()->motivo);
        $this->assertEquals($movimientos, DB::table('movimientos_inventario')->get()->toArray());
        $this->assertSame(32, (int) Inventario::sole()->cantidad_actual);
        $despacho = ['version_pedido' => $pedido->version, 'operacion' => 'pr45-despacho-unico-5432', 'motivo' => 'Se despachan las 32 unidades efectivamente envasadas.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 32]]];
        $servicio = app(AtenderPedidoUnidades::class);
        $id = $servicio->despachar($pedido, $despacho, $e['usuario']);
        $this->assertSame($id, $servicio->despachar($pedido, $despacho, $e['usuario']));
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 0]);
        $this->assertDatabaseCount('pedido_despachos', 1);
        $this->assertDatabaseCount('movimientos_inventario', 3);
        $pedido->refresh();
        $control = app(ControlEntregaPedido::class);
        $fisica = array_values($control->resumen($pedido)['lineas'])[0];
        app(RegistrarResultadoEntrega::class)->ejecutar($pedido, ['version' => $control->resumen($pedido)['version'],
            'observaciones' => 'Resultado registrado tras explicar al cliente el ajuste de producción.',
            'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aceptadas' => $aceptadas, 'rechazadas' => $rechazadas, 'motivo' => 'El cliente no acepta estas unidades; se registra sin atribuir responsabilidades.']]], $e['usuario']);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 0]);
        if ($rechazadas > 0) {
            app(RegistrarRetornoPedido::class)->ejecutar($pedido->fresh(), ['version' => $control->resumen($pedido)['version'], 'observaciones' => 'Se reciben físicamente las unidades rechazadas; una está dañada.',
                'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aptas' => $rechazadas - 1, 'danadas' => 1, 'en_revision' => 0, 'no_recuperadas' => 0]]], $e['usuario']);
            $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 0]);
            $retorno = DB::table('retorno_pedido_detalles')->sole();
            app(AprobarRetornoPedido::class)->ejecutar($pedido->fresh(), ['version' => $control->resumen($pedido)['version'], 'retorno_detalle_id' => $retorno->id,
                'cantidad' => $rechazadas - 1, 'motivo' => 'Se aprueban únicamente las unidades físicamente recuperadas y aptas.'], $e['usuario']);
            $this->assertSame($rechazadas - 1, (int) Inventario::sole()->cantidad_actual);
            $this->assertDatabaseCount('movimientos_inventario', 4);
        }
        if ($retirarEstructuras) {
            foreach (array_keys(FilasIndicadores::REPORTES) as $reporte) {
                $filtros = ['reporte' => $reporte, 'economico' => 1];
                $this->get(route('indicadores.index', $filtros))->assertOk();
                $csv = $this->get(route('indicadores.exportar', $filtros))->assertOk()->streamedContent();
                $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            }
            $consultas = DB::getQueryLog();
            DB::disableQueryLog();
            foreach ($consultas as $consulta) {
                $this->assertDoesNotMatchRegularExpression('/\b(formatos_surtidos|formato_surtido_skus|pedido_paquetes_surtidos|paquete_surtido_composiciones|paquete_surtido_componentes|pedido_despacho_componentes)\b/i', $consulta['query']);
            }
        }
        foreach ($cuentas as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
        $this->assertSame('68.000', $e['insumo']->fresh()->cantidad_actual);
        $this->assertDatabaseCount('envasados', 1);
    }
}
