<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;
use Tests\VentaUnidadesEscenario;

class PrepararSaneamientoComercialTest extends TestCase
{
    use RefreshDatabase, VentaUnidadesEscenario;

    public function test_prepara_conteos_y_dependencias_sin_eliminar_datos_ni_exponer_cuentas(): void
    {
        $e = $this->escenarioUnidades();
        $this->registrarUnidades($e);
        $originales = [];
        foreach (Schema::getTableListing(null, false) as $tabla) {
            $originales[$tabla] = DB::table($tabla)->get()->toArray();
        }
        $this->assertSame(0, Artisan::call('comercio:preparar-saneamiento', ['--base' => ':memory:']));
        $salida = Artisan::output();
        $plan = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('SOLO_LECTURA_NO_EJECUTA_LIMPIEZA', $plan['modo']);
        $this->assertSame('conservar íntegramente', $plan['tablas']['users']['tratamiento']);
        $this->assertSame(1, $plan['tablas']['pedidos']['registros']);
        $this->assertLessThan(array_search('pedidos', $plan['orden_candidato_hijas_antes_de_padres'], true), array_search('pedido_detalles', $plan['orden_candidato_hijas_antes_de_padres'], true));
        $this->assertStringNotContainsString($e['usuario']->email, $salida);
        $this->assertStringNotContainsString($e['usuario']->password, $salida);
        foreach ($originales as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
    }

    public function test_una_base_distinta_aborta_sin_cambios(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Identidad de base no autorizada');
        Artisan::call('comercio:preparar-saneamiento', ['--base' => 'software_gestion_web_microempresa']);
    }

    public function test_una_tabla_desconocida_impide_preparar_el_saneamiento(): void
    {
        Schema::create('datos_no_clasificados', fn (Blueprint $tabla) => $tabla->id());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tablas sin clasificar');
        Artisan::call('comercio:preparar-saneamiento', ['--base' => ':memory:']);
    }

    public function test_dependencia_de_cuentas_bloquea_la_limpieza_propuesta(): void
    {
        Schema::table('users', fn (Blueprint $tabla) => $tabla->foreignId('cliente_referido_id')->nullable()->constrained('clientes'));
        $this->assertSame(1, Artisan::call('comercio:preparar-saneamiento', ['--base' => ':memory:']));
        $this->assertSame(['users → clientes'], json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['bloqueos']);
    }
}
