<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidacionClientesTest extends TestCase
{
    use RefreshDatabase;

    private function autenticar(): void
    {
        $rol = Role::create(['nombre' => 'Operaciones Comerciales']);
        foreach (['clientes.crear', 'clientes.editar'] as $nombre) {
            $rol->permisos()->attach(Permiso::create(['nombre' => $nombre])->id);
        }
        $this->actingAs(User::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'clientes@local.test', 'password' => 'Prueba12345', 'role_id' => $rol->id, 'status' => true]));
    }

    public static function validos(): array
    {
        return [
            'opcionales vacíos' => [null, null, null],
            'DNI y móvil' => ['12345678', '987 654 321', '987654321'],
            'RUC 10' => ['10123456789', '+51 987-654-321', '987654321'],
            'RUC 15' => ['15123456789', '(01) 234-5678', '012345678'],
            'RUC 17' => ['17123456789', '+51 01 2345678', '012345678'],
            'RUC 20' => ['20123456789', '+51 1 2345678', '012345678'],
            'CE numérico' => ['123456789', null, null],
            'CE alfanumérico' => ['CE1234567890', null, null],
        ];
    }

    #[DataProvider('validos')]
    public function test_registra_datos_validos_y_normaliza_telefono(?string $documento, ?string $telefono, ?string $normalizado): void
    {
        $this->autenticar();

        $this->post(route('clientes.store'), ['nombre' => "Comercial Ñandú & D'Ávila S.R.L.", 'documento' => $documento, 'telefono' => $telefono, 'correo' => 'ventas+pedidos@ejemplo.com'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('clientes', ['documento' => $documento, 'telefono' => $normalizado, 'correo' => 'ventas+pedidos@ejemplo.com']);
    }

    public static function invalidos(): array
    {
        return [
            'nombre corto' => ['nombre', 'Ab'],
            'espacios dobles' => ['nombre', 'Ana  Pérez'],
            'sin letras' => ['nombre', '123'],
            'símbolos' => ['nombre', 'Ana <script>'],
            'DNI corto' => ['documento', '1234567'],
            'RUC prefijo inválido' => ['documento', '30123456789'],
            'CE largo' => ['documento', 'CE12345678901'],
            'CE símbolo' => ['documento', 'CE-1234567'],
            'móvil no peruano' => ['telefono', '887654321'],
            'móvil corto' => ['telefono', '98765432'],
            'fijo corto' => ['telefono', '01234567'],
            'prefijo extranjero' => ['telefono', '+52 987654321'],
            'teléfono con letras' => ['telefono', '987abc654321'],
            'correo inválido' => ['correo', 'correo inválido'],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_rechaza_datos_invalidos_sin_crear_cliente(string $campo, string $valor): void
    {
        $this->autenticar();

        $this->post(route('clientes.store'), array_replace(['nombre' => 'Cliente Válido'], [$campo => $valor]))
            ->assertSessionHasErrors($campo);

        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_documento_unico_ignora_al_cliente_editado_y_admite_varios_nulos(): void
    {
        $this->autenticar();
        $cliente = Cliente::create(['nombre' => 'Cliente Uno', 'documento' => '12345678']);
        $otro = Cliente::create(['nombre' => 'Cliente Dos']);
        Cliente::create(['nombre' => 'Cliente Tres']);

        $this->put(route('clientes.update', $cliente), ['nombre' => 'Cliente Actualizado', 'documento' => '12345678'])->assertSessionHasNoErrors();
        $this->post(route('clientes.store'), ['nombre' => 'Cliente Nuevo', 'documento' => '12345678'])->assertSessionHasErrors('documento');
        $this->put(route('clientes.update', $otro), ['nombre' => 'Cliente Dos', 'documento' => '12345678'])->assertSessionHasErrors('documento');

        $this->assertDatabaseCount('clientes', 3);
        $this->assertNull($otro->fresh()->documento);
    }

    public function test_migracion_aborta_y_lista_duplicados_sin_perder_datos(): void
    {
        $migracion = require database_path('migrations/2026_10_19_115153_add_unique_documento_to_clientes_table.php');
        $migracion->down();
        $uno = Cliente::create(['nombre' => 'Cliente Uno', 'documento' => '12345678']);
        $dos = Cliente::create(['nombre' => 'Cliente Dos', 'documento' => '12345678']);

        try {
            $migracion->up();
            $this->fail('La migración debía detectar los documentos duplicados.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('12345678', $exception->getMessage());
            $this->assertStringContainsString('clientes: '.$uno->id.', '.$dos->id, $exception->getMessage());
        }

        $this->assertDatabaseCount('clientes', 2);
        $this->assertSame('12345678', $uno->fresh()->documento);
        $this->assertSame('12345678', $dos->fresh()->documento);
    }
}
