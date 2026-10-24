<?php

namespace Tests\Feature;

use App\Services\ConfigurarFormatoSurtido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\SurtidosEscenario;
use Tests\TestCase;

class SurtidosTest extends TestCase
{
    use RefreshDatabase, SurtidosEscenario;

    public function test_habilitar_surtido_no_crea_productos_recetas_ni_inventario(): void
    {
        $e = $this->escenarioSurtido();
        $this->assertDatabaseCount('productos', 1);
        $this->assertDatabaseCount('presentaciones_producto', 3);
        $this->assertDatabaseCount('formato_surtido_skus', 3);
        $this->assertDatabaseCount('pedido_paquetes_surtidos', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame('24.00', number_format((float) DB::table('formatos_surtidos')->value('precio_empaque'), 2, '.', ''));
    }

    public function test_configuracion_rechaza_capacidades_incompatibles_y_no_sobrescribe_una_version_obsoleta(): void
    {
        $e = $this->escenarioSurtido();
        $e['skus'][1]->update(['contenido' => 2]);
        $this->expectException(ValidationException::class);
        app(ConfigurarFormatoSurtido::class)->guardar(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'No permitido', 'unidades_por_empaque' => 12, 'precio_empaque' => 24, 'estado' => true, 'version' => 0, 'skus' => [$e['skus'][0]->id, $e['skus'][1]->id]], $e['usuario'], $e['formato']);
    }
}
