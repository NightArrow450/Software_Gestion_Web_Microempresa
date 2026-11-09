<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_ruta_principal_redirige_al_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_la_pagina_de_login_se_muestra_correctamente(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }
}
