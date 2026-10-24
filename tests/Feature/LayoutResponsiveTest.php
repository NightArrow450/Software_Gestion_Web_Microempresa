<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutResponsiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_menu_del_layout_controla_un_sidebar_existente(): void
    {
        $role = Role::create(['nombre' => 'Administrador', 'es_sistema' => true]);
        $user = User::create([
            'first_name' => 'Persona', 'last_name' => 'Prueba',
            'email' => 'layout@local.test', 'password' => 'password123',
            'role_id' => $role->id, 'status' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $buttons = $xpath->query('//header//button[@aria-controls]');
        $this->assertSame(1, $buttons->length);
        $button = $buttons->item(0);
        $this->assertInstanceOf(DOMElement::class, $button);
        $this->assertSame('false', $button->getAttribute('aria-expanded'));
        $this->assertSame('Abrir menú de navegación', $button->getAttribute('aria-label'));
        $sidebar = $xpath->query('//aside[@id]');
        $this->assertSame(1, $sidebar->length);
        $elementoSidebar = $sidebar->item(0);
        $this->assertInstanceOf(DOMElement::class, $elementoSidebar);
        $this->assertSame($elementoSidebar->getAttribute('id'), $button->getAttribute('aria-controls'));
        $this->assertSame(1, $xpath->query('//script[contains(@src, "/js/layout.js") and @defer]')->length);
    }
}
