<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidacionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function datos(): array
    {
        $rol = Role::create(['nombre' => 'Administrador']);
        $this->actingAs(User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'admin@local.test', 'password' => 'Prueba12345', 'role_id' => $rol->id, 'status' => true]));

        return ['first_name' => 'María-José', 'last_name' => "D'Ávila", 'email' => 'Nueva@Ejemplo.COM', 'password' => 'Segura12345', 'password_confirmation' => 'Segura12345', 'role_id' => $rol->id, 'status' => 1];
    }

    public function test_registra_nombres_unicode_correo_minusculo_y_contrasena_segura(): void
    {
        $datos = $this->datos();
        $this->mock(UncompromisedVerifier::class)->shouldNotReceive('verify');
        $this->post(route('usuarios.store'), $datos)->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'nueva@ejemplo.com')->firstOrFail();
        $this->assertSame('María-José', $usuario->first_name);
        $this->assertTrue(Hash::check('Segura12345', $usuario->password));
    }

    public static function invalidos(): array
    {
        return [
            'nombre corto' => ['first_name', 'A'],
            'apellido corto' => ['last_name', 'B'],
            'nombre con números' => ['first_name', 'Ana2'],
            'apellido con símbolos' => ['last_name', 'Pérez@'],
            'correo inválido' => ['email', 'correo inválido'],
            'contraseña de nueve' => ['password', 'Abcdefg12'],
            'sin letras' => ['password', '1234567890'],
            'sin mayúsculas' => ['password', 'abcdefgh12'],
            'sin minúsculas' => ['password', 'ABCDEFGH12'],
            'sin números' => ['password', 'Abcdefghij'],
        ];
    }

    public function test_acepta_nombres_de_dos_letras_y_contrasena_de_diez_caracteres(): void
    {
        $datos = array_replace($this->datos(), ['first_name' => 'Li', 'last_name' => 'Wu', 'password' => 'Abcdefgh12', 'password_confirmation' => 'Abcdefgh12']);

        $this->post(route('usuarios.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['first_name' => 'Li', 'last_name' => 'Wu']);
    }

    public function test_conserva_la_confirmacion_de_contrasena(): void
    {
        $datos = $this->datos();
        $datos['password_confirmation'] = 'OtraSegura123';

        $this->post(route('usuarios.store'), $datos)->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('invalidos')]
    public function test_rechaza_datos_invalidos_en_alta_y_edicion(string $campo, string $valor): void
    {
        $datos = $this->datos();
        $usuario = User::firstOrFail();
        $datos[$campo] = $valor;
        if ($campo === 'password') {
            $datos['password_confirmation'] = $valor;
        }

        $this->post(route('usuarios.store'), $datos)->assertSessionHasErrors($campo);
        $this->put(route('usuarios.update', $usuario), $datos)->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('admin@local.test', $usuario->fresh()->email);
    }

    public function test_correo_unico_sin_mayusculas_ignora_al_propio_y_conserva_contrasena_vacia(): void
    {
        $datos = $this->datos();
        $usuario = User::create(array_replace($datos, ['email' => 'Historico@Ejemplo.COM']));
        $hash = $usuario->password;
        $datos['email'] = 'HISTORICO@EJEMPLO.COM';
        $this->post(route('usuarios.store'), $datos)->assertSessionHasErrors('email');
        $this->put(route('usuarios.update', User::firstOrFail()), $datos)->assertSessionHasErrors('email');

        $datos['password'] = '';
        $datos['password_confirmation'] = '';
        $this->put(route('usuarios.update', $usuario), $datos)->assertSessionHasNoErrors();
        $this->assertSame('historico@ejemplo.com', $usuario->fresh()->email);
        $this->assertSame($hash, $usuario->fresh()->password);
    }

    public static function filtraciones(): array
    {
        return ['segura' => [true], 'comprometida' => [false]];
    }

    #[DataProvider('filtraciones')]
    public function test_produccion_consulta_filtraciones_sin_conexion_externa(bool $segura): void
    {
        $datos = $this->datos();
        $this->app->instance('env', 'production');
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->mock(UncompromisedVerifier::class)->shouldReceive('verify')->once()->with(['value' => 'Segura12345', 'threshold' => 0])->andReturn($segura);

        $respuesta = $this->post(route('usuarios.store'), $datos);

        if ($segura) {
            $respuesta->assertSessionHasNoErrors();
            $this->assertDatabaseCount('users', 2);
        } else {
            $respuesta->assertSessionHasErrors('password');
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_login_admite_correo_con_distinta_capitalizacion(): void
    {
        $rol = Role::create(['nombre' => 'Administrador']);
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'Historico@Ejemplo.COM', 'password' => 'Segura12345', 'role_id' => $rol->id, 'status' => true]);

        $this->post(route('login.authenticate'), ['email' => 'HISTORICO@EJEMPLO.COM', 'password' => 'Segura12345'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }
}
