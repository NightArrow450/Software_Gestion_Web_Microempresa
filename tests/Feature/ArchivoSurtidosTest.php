<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\SurtidosEscenario;
use Tests\TestCase;

class ArchivoSurtidosTest extends TestCase
{
    use RefreshDatabase, SurtidosEscenario;

    public function test_archivo_pagina_formatos_sin_repetir_el_catalogo_completo(): void
    {
        $e = $this->escenarioSurtido();
        $formato = (array) DB::table('formatos_surtidos')->first();
        unset($formato['id']);
        for ($i = 1; $i <= 45; $i++) {
            DB::table('formatos_surtidos')->insert(array_replace($formato, ['nombre' => sprintf('Formato %03d', $i)]));
        }
        DB::enableQueryLog();
        $inicio = hrtime(true);
        $respuesta = $this->get(route('surtidos.index'));
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();
        $respuesta->assertOk()->assertSee('Archivo de modalidades surtidas')->assertSee('Formato 020')->assertDontSee('Formato 021')->assertDontSee('name="skus[]"', false);
        $this->assertLessThan(20, $consultas);
        $this->assertLessThan(60000, strlen($respuesta->getContent()));
        fwrite(STDERR, json_encode(['medicion' => 'archivo_surtidos', 'formatos' => 46, 'consultas' => $consultas, 'milisegundos' => round((hrtime(true) - $inicio) / 1000000, 2), 'bytes_html' => strlen($respuesta->getContent()), 'memoria_pico_bytes' => memory_get_peak_usage(true)]).PHP_EOL);
        $this->get(route('surtidos.index', ['page' => 3]))->assertOk()->assertSee('Surtido de prueba 1 L');
        $this->assertSame(46, DB::table('formatos_surtidos')->count());
        $this->assertDatabaseCount('paquete_surtido_componentes', 0);
    }

    public function test_el_archivo_conserva_la_restriccion_de_permiso(): void
    {
        $e = $this->escenarioSurtido();
        $e['usuario']->role->permisos()->detach();
        $this->get(route('surtidos.index'))->assertForbidden();
    }
}
