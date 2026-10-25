<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\RolesEscenario;
use Tests\TestCase;

class SesionUsuarioActivoTest extends TestCase
{
    use RefreshDatabase, RolesEscenario;

    #[DataProvider('roles')]
    public function test_usuario_activo_inicia_sesion_y_conserva_acceso_autorizado(string $rol): void
    {
        $this->instalarRolesDePrueba();
        $usuario = User::factory()->create(['role_id' => $this->rolDePrueba($rol)->id]);

        $this->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'ClavePruebaSegura123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('pedidos.index'))->assertOk();
        $this->get(route('usuarios.index'))->assertStatus($rol === 'Administrador' ? 200 : 403);
    }

    public static function roles(): array
    {
        return [['Administrador'], ['Operaciones Comerciales'], ['Producción/Reparto']];
    }

    public function test_desactivacion_invalida_sesion_token_y_acceso_a_otros_modulos(): void
    {
        $this->instalarRolesDePrueba();
        $usuario = User::factory()->create(['role_id' => $this->rolDePrueba('Operaciones Comerciales')->id]);
        $this->post(route('login.authenticate'), ['email' => $usuario->email, 'password' => 'ClavePruebaSegura123'])->assertRedirect(route('dashboard'));
        $sesion = app('session')->driver();
        $sesion->put('dato_privado_de_sesion', 'solo para esta prueba');
        $identificador = $sesion->getId();
        $token = $sesion->token();
        $sesion->save();
        $usuario->update(['status' => false]);
        Auth::forgetGuards();

        $this->withCookie(config('session.cookie'), $identificador)
            ->get(route('dashboard'))->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Tu cuenta se encuentra inactiva. Comunícate con el administrador.'])
            ->assertSessionMissing('dato_privado_de_sesion');
        $this->assertGuest();
        $this->assertNotSame($identificador, $sesion->getId());
        $this->assertNotSame($token, $sesion->token());
        $this->assertEmpty($sesion->getHandler()->read($identificador));

        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $identificador)
            ->get(route('pedidos.index'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('login'))->assertOk();
    }

    public function test_dos_sesiones_existentes_se_expulsan_al_realizar_su_siguiente_solicitud(): void
    {
        $this->instalarRolesDePrueba();
        $usuario = User::factory()->create(['role_id' => $this->rolDePrueba('Operaciones Comerciales')->id]);
        $sesion = app('session')->driver();
        $identificadores = [];
        foreach (range(1, 2) as $dispositivo) {
            $sesion->flush();
            $sesion->migrate(false);
            $sesion->put(Auth::guard('web')->getName(), $usuario->id);
            $sesion->put('dispositivo', $dispositivo);
            $sesion->save();
            $identificadores[] = $sesion->getId();
        }
        $this->assertNotSame($identificadores[0], $identificadores[1]);
        foreach ($identificadores as $indice => $identificador) {
            Auth::forgetGuards();
            $this->withCookie(config('session.cookie'), $identificador)
                ->get(route('dashboard'))->assertOk()->assertSessionHas('dispositivo', $indice + 1);
            $this->assertAuthenticatedAs($usuario);
        }
        $usuario->update(['status' => false]);

        foreach ($identificadores as $identificador) {
            Auth::forgetGuards();
            $this->withCookie(config('session.cookie'), $identificador)
                ->get(route('dashboard'))->assertRedirect(route('login'))
                ->assertSessionHasErrors('email')->assertSessionMissing('dispositivo');
            $this->assertGuest();
            $this->assertNotSame($identificador, $sesion->getId());
            $this->assertEmpty($sesion->getHandler()->read($identificador));
        }
    }

    public function test_solicitud_json_de_cuenta_desactivada_recibe_401_sin_redireccion(): void
    {
        $this->instalarRolesDePrueba();
        $usuario = User::factory()->create(['role_id' => $this->rolDePrueba('Administrador')->id, 'status' => false]);
        $this->actingAs($usuario)->withSession(['dato_privado_de_sesion' => 'prueba']);

        $this->getJson(route('pedidos.index'))->assertUnauthorized()
            ->assertExactJson(['mensaje' => 'Tu cuenta se encuentra inactiva. Comunícate con el administrador.'])
            ->assertHeaderMissing('Location')->assertSessionMissing('dato_privado_de_sesion');
        $this->assertGuest();
    }

    public function test_cuenta_inactiva_no_puede_eludir_control_por_cambio_de_password(): void
    {
        $this->instalarRolesDePrueba();
        $usuario = User::factory()->create(['role_id' => $this->rolDePrueba('Administrador')->id, 'status' => false, 'debe_cambiar_password' => true]);
        $this->actingAs($usuario)->get(route('password.cambiar'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_rutas_publicas_siguen_disponibles_y_login_no_entra_en_un_ciclo(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('salud'))->assertOk();
        $this->get('/')->assertRedirect(route('login'));
        $this->instalarRolesDePrueba();
        $usuario = User::factory()->create(['role_id' => $this->rolDePrueba('Administrador')->id, 'status' => false]);
        $this->actingAs($usuario)->get(route('login'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('login'))->assertOk();
    }
}
