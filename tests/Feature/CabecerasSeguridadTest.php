<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CabecerasSeguridadTest extends TestCase
{
    public function test_cabeceras_y_csp_report_only_en_http_local(): void
    {
        $respuesta = $this->get('/login')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security')
            ->assertHeaderMissing('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $respuesta->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_hsts_solo_en_produccion_aun_si_local_usa_https(): void
    {
        $this->get('https://localhost/login')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
        $this->app->instance('env', 'production');
        $this->get('https://localhost/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/login')->assertStatus(308)->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public static function errores(): array
    {
        return [[403], [404], [419], [500], [503]];
    }

    #[DataProvider('errores')]
    public function test_las_paginas_de_error_tambien_tienen_cabeceras(int $codigo): void
    {
        Route::get('/error-cabeceras', fn () => abort($codigo));
        $this->get('/error-cabeceras')->assertStatus($codigo)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy-Report-Only')
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
