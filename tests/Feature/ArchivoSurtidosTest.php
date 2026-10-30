<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\VentaUnidadesEscenario;

class ArchivoSurtidosTest extends TestCase
{
    use RefreshDatabase, VentaUnidadesEscenario;

    public function test_retirar_el_archivo_no_borra_sus_datos_y_el_formulario_no_consulta_surtidos(): void
    {
        $e = $this->escenarioUnidades();
        for ($i = 0; $i < 46; $i++) {
            DB::table('formatos_surtidos')->insert(['producto_id' => $e['presentacion']->producto_id, 'nombre' => sprintf('Referencia anterior %03d', $i),
                'tipo_envase' => 'Botella', 'contenido' => 1, 'unidad_medida' => 'L', 'unidades_por_empaque' => 12, 'precio_empaque' => 24, 'estado' => true]);
        }
        $antes = DB::table('formatos_surtidos')->orderBy('id')->get()->toArray();
        DB::enableQueryLog();

        $respuesta = $this->get(route('pedidos.create'));

        $respuesta->assertOk()->assertSee('Pedidos por unidades físicas')->assertDontSee('Archivo de surtidos')->assertDontSee('formatosSurtidos');
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($consultas as $consulta) {
            $this->assertDoesNotMatchRegularExpression('/formatos_surtidos|formato_surtido_skus|pedido_paquetes_surtidos|paquete_surtido_composiciones|paquete_surtido_componentes|pedido_despacho_componentes/i', $consulta['query']);
        }
        $this->assertLessThan(20, count($consultas));
        $this->assertLessThan(60000, strlen($respuesta->getContent()));
        $this->get('/modalidades-surtidas')->assertNotFound();
        $this->assertEquals($antes, DB::table('formatos_surtidos')->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('paquete_surtido_componentes', 0);
    }

    public function test_permiso_anterior_no_reabre_rutas_y_se_conserva_la_autorizacion_del_formulario(): void
    {
        $e = $this->escenarioUnidades();
        $e['usuario']->role->permisos()->detach();
        $e['usuario']->unsetRelation('role');
        $this->get('/modalidades-surtidas')->assertNotFound();
        $this->get(route('pedidos.create'))->assertForbidden();
        $this->assertDatabaseCount('pedidos', 0);
    }
}
