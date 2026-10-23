<?php

namespace Tests\Feature;

use App\Models\Permiso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class EntregasRetornosTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    public function test_flujo_existente_funciona_antes_de_aplicar_las_nuevas_migraciones(): void
    {
        $e = $this->escenarioEnvasado(40, 50);
        $e['usuario']->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => 'cumplimiento.entregar'], ['descripcion' => 'Registrar entrega'])->id);
        $pedido = $this->pedidoEnvasado($e, 'listo_despacho');
        $migraciones = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $ruta): bool => str_contains($ruta, 'create_incidencias_y_entregas') || str_contains($ruta, 'create_retornos_pedido') || str_contains($ruta, 'create_incidencia_entrega_eventos')));
        foreach (array_reverse($migraciones) as $ruta) {
            (require $ruta)->down();
        }
        try {
            $this->actingAs($e['usuario'])->post(route('cumplimiento.despachar', $pedido))->assertRedirect();
            $this->get(route('cumplimiento.show', $pedido))->assertOk();
            $this->post(route('cumplimiento.resultado-entrega', $pedido), [
                'version' => 0,
                'detalles' => [['pedido_detalle_id' => $pedido->detalles->first()->id, 'aceptadas' => 5, 'rechazadas' => 0]],
            ])->assertStatus(503);
            $this->post(route('cumplimiento.entregar', $pedido))->assertRedirect();
            $this->assertSame('entregado', $pedido->fresh()->estado);
            $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 45]);
            $this->assertDatabaseCount('movimientos_inventario', 1);
        } finally {
            foreach ($migraciones as $ruta) {
                (require $ruta)->up();
            }
        }
    }

    public function test_migraciones_son_aditivas_y_conservan_datos_anteriores(): void
    {
        $e = $this->escenarioEnvasado(40, 50);
        $this->pedidoEnvasado($e, 'despachado');
        $tablas = ['users', 'clientes', 'productos', 'presentaciones_producto', 'inventarios', 'insumos', 'pedidos', 'pedido_detalles', 'movimientos_inventario'];
        $antes = [];
        foreach ($tablas as $tabla) {
            $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }
        $migraciones = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $ruta): bool => str_contains($ruta, 'create_incidencias_y_entregas') || str_contains($ruta, 'create_retornos_pedido') || str_contains($ruta, 'create_incidencia_entrega_eventos')));
        foreach (array_reverse($migraciones) as $ruta) {
            (require $ruta)->down();
        }
        foreach ($migraciones as $ruta) {
            (require $ruta)->up();
        }
        foreach ($tablas as $tabla) {
            $this->assertSame($antes[$tabla], DB::table($tabla)->orderBy('id')->get()->toJson(), $tabla);
        }
        foreach (['incidencias_entrega', 'entregas_pedido', 'entrega_pedido_detalles', 'retornos_pedido', 'retorno_pedido_detalles', 'incidencia_entrega_eventos'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
    }
}
