<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\RolesEscenario;
use Tests\TestCase;

class AdministracionFuncionalTest extends TestCase
{
    use RefreshDatabase, RolesEscenario;

    private function administrador(): User
    {
        $this->instalarRolesDePrueba();
        $admin = User::factory()->create(['role_id' => $this->rolDePrueba('Administrador')->id]);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_categoria_invalida_y_escritura_sin_permiso_no_crean_registros(): void
    {
        $this->administrador();
        $this->postJson(route('categorias.store'), ['codigo' => 'INVALIDO', 'nombre' => '', 'estado' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['codigo', 'nombre']);
        $produccion = User::factory()->create(['role_id' => $this->rolDePrueba('Producción/Reparto')->id]);
        $this->actingAs($produccion)->post(route('categorias.store'), ['codigo' => 'CAT902', 'nombre' => 'Categoría no autorizada', 'estado' => 1])->assertForbidden();
        $this->assertDatabaseCount('categorias', 0);
    }

    public function test_nombres_invalidos_de_rol_y_permiso_se_rechazan_sin_persistir(): void
    {
        $this->administrador();
        $roles = Role::count();
        $permisos = Permiso::count();
        $this->postJson(route('roles.store'), ['nombre' => ''])->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->postJson(route('permisos.store'), ['nombre' => 'SINPUNTO'])->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->assertSame($roles, Role::count());
        $this->assertSame($permisos, Permiso::count());
    }

    public function test_usuario_se_registra_edita_y_desactiva_sin_cambiar_su_password(): void
    {
        $this->administrador();
        $datos = ['first_name' => 'Elena', 'last_name' => 'Prueba', 'email' => 'elena@example.test', 'role_id' => $this->rolDePrueba('Operaciones Comerciales')->id, 'status' => 1, 'password' => 'ClaveFicticia123', 'password_confirmation' => 'ClaveFicticia123'];
        $this->post(route('usuarios.store'), $datos)->assertSessionHasNoErrors()->assertRedirect();
        $u = User::where('email', 'elena@example.test')->firstOrFail();
        $hash = $u->password;
        $this->get(route('usuarios.show', $u))->assertOk()->assertSee('Elena');
        unset($datos['password'],$datos['password_confirmation']);
        $datos['last_name'] = 'Ejemplo';
        $this->put(route('usuarios.update', $u), $datos)->assertSessionHasNoErrors()->assertRedirect();
        $this->patch(route('usuarios.cambiar-estado', $u))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Ejemplo', $u->fresh()->last_name);
        $this->assertFalse($u->fresh()->status);
        $this->assertSame($hash, $u->fresh()->password);
    }

    public function test_permiso_y_rol_personalizados_se_gestionan_sin_alterar_roles_base(): void
    {
        $admin = $this->administrador();
        $this->post(route('permisos.store'), ['nombre' => 'fase9a.consultar', 'descripcion' => 'Permiso ficticio de prueba'])->assertSessionHasNoErrors()->assertRedirect();
        $p = Permiso::where('nombre', 'fase9a.consultar')->firstOrFail();
        $this->assertTrue($admin->role->tienePermiso($p->nombre));
        $this->post(route('roles.store'), ['nombre' => 'Rol ficticio de revisión', 'permisos' => [$p->id]])->assertSessionHasNoErrors()->assertRedirect();
        $r = Role::where('nombre', 'Rol ficticio de revisión')->firstOrFail();
        $this->assertTrue($r->tienePermiso($p->nombre));
        $this->put(route('roles.update', $r), ['nombre' => 'Rol ficticio actualizado', 'permisos' => []])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($r->fresh()->tienePermiso($p->nombre));
        $this->delete(route('roles.destroy', $r))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSoftDeleted('roles', ['id' => $r->id]);
        $this->put(route('permisos.update', $p), ['nombre' => 'fase9a.revisar', 'descripcion' => 'Permiso ficticio actualizado'])->assertSessionHasNoErrors()->assertRedirect();
        $this->delete(route('permisos.destroy', $p))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSoftDeleted('permisos', ['id' => $p->id]);
        $this->assertDatabaseHas('roles', ['id' => $admin->role_id, 'nombre' => 'Administrador', 'deleted_at' => null]);
    }

    public function test_categoria_y_producto_se_crean_editan_y_desactivan_sin_eliminar_presentaciones(): void
    {
        $this->administrador();
        $this->post(route('categorias.store'), ['codigo' => 'CAT901', 'nombre' => 'Categoría ficticia final', 'estado' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $c = Categoria::where('codigo', 'CAT901')->firstOrFail();
        $this->put(route('categorias.update', $c), ['codigo' => 'CAT901', 'nombre' => 'Categoría ficticia revisada', 'estado' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $datos = ['codigo' => 'PRUEBA-FINAL', 'categoria_id' => $c->id, 'nombre' => 'Producto ficticio final', 'estado' => 1, 'prefijo_sku' => 'FINAL', 'presentaciones' => [['contenido' => 1, 'unidad_medida' => 'L', 'tipo_empaque' => 'Caja', 'unidades_por_empaque' => 12, 'venta_por_unidad' => 1, 'venta_por_empaque' => 1, 'precio_unitario' => 2.5, 'estado' => 1]]];
        $this->post(route('productos.store'), $datos)->assertSessionHasNoErrors()->assertRedirect();
        $p = Producto::where('codigo', 'PRUEBA-FINAL')->firstOrFail();
        $this->get(route('productos.show', $p))->assertOk()->assertSee('Producto ficticio final');
        $datos['nombre'] = 'Producto ficticio revisado';
        $datos['_version'] = $p->version;
        $datos['_updated_at'] = $p->getRawOriginal('updated_at');
        $datos['presentaciones'][0]['ids'] = ['__none__' => $p->presentaciones()->sole()->id];
        $this->put(route('productos.update', $p), $datos)->assertSessionHasNoErrors()->assertRedirect();
        $this->patch(route('productos.cambiar-estado', $p))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($p->fresh()->estado);
        $this->assertSame('Producto ficticio revisado', $p->fresh()->nombre);
        $this->assertGreaterThan(0, $p->presentaciones()->count());
        $this->patch(route('categorias.cambiar-estado', $c))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($c->fresh()->estado);
    }
}
