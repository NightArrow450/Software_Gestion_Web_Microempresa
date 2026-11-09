<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BloqueoOptimistaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_formulario_obsoleto_incluso_en_el_mismo_segundo(): void
    {
        $this->freezeTime();
        $rol = Role::create(['nombre' => 'Administrador', 'es_sistema' => true]);
        foreach (['clientes.ver', 'clientes.editar'] as $nombre) {
            $rol->permisos()->attach(Permiso::create(['nombre' => $nombre, 'descripcion' => $nombre])->id);
        }
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => 'version@test.local', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
        $cliente = Cliente::create(['nombre' => 'Cliente inicial', 'estado' => true])->fresh();
        $this->actingAs($usuario)->get(route('clientes.edit', $cliente))->assertOk()->assertSee('name="_version"', false)->assertSee('name="_updated_at"', false);
        $datos = ['nombre' => 'Cambio primero', '_version' => $cliente->version, '_updated_at' => $cliente->getRawOriginal('updated_at')];
        $this->actingAs($usuario)->put(route('clientes.update', $cliente), $datos)->assertRedirect();
        $this->assertSame($datos['_updated_at'], $cliente->fresh()->getRawOriginal('updated_at'));
        $datos['nombre'] = 'Cambio obsoleto';
        $this->put(route('clientes.update', $cliente), $datos)->assertSessionHasErrors('edicion');
        $this->assertSame('Cambio primero', $cliente->fresh()->nombre);
        $this->assertSame(1, (int) $cliente->fresh()->version);
    }
}
