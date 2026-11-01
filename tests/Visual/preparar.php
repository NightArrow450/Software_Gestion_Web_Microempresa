<?php

use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use App\Services\AtenderPedidoUnidades;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarPedidoUnidades;
use App\Services\RegistrarResultadoEntrega;
use Database\Seeders\EntregaRetornoPermisoSeeder;
use Database\Seeders\IndicadoresPermisoSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\VentaUnidadesEscenario;

require __DIR__.'/entorno.php';
$base = (string) getenv('VISUAL_DATABASE');
if (PHP_SAPI !== 'cli' || ! is_file($base) || filesize($base) !== 0) {
    throw new RuntimeException('Se exige un archivo SQLite nuevo y vacío; no se limpia ninguna base.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    throw new RuntimeException('Falló la migración visual; no se reintenta.');
}
$escenario = new class
{
    use VentaUnidadesEscenario;

    public function actingAs(User $usuario): self
    {
        Auth::setUser($usuario);

        return $this;
    }

    public function preparar(): array
    {
        $e = $this->escenarioUnidades(32);
        config(['envasado.control_materiales_exacto' => false]);
        foreach ([RoleSeeder::class, PermisoSeeder::class, EntregaRetornoPermisoSeeder::class,
            IndicadoresPermisoSeeder::class] as $clase) {
            (new $clase)->run();
        }
        $rol = Role::where('nombre', 'Administrador')->sole();
        $rol->permisos()->syncWithoutDetaching(Permiso::pluck('id')->all());
        $e['usuario']->update(['email' => 'visual@example.test', 'first_name' => 'Ana', 'last_name' => 'Demostración',
            'password' => getenv('VISUAL_PASSWORD'), 'debe_cambiar_password' => false, 'role_id' => $rol->id]);
        $e['usuario']->unsetRelation('role');
        $e['presentacion']->producto->update(['nombre' => 'Quitásarro ficticio', 'codigo' => 'VISUAL-QTS']);
        Cliente::create(['nombre' => 'JOSÉ NÚÑEZ — cliente ficticio', 'estado' => true]);
        for ($i = 1; $i <= 22; $i++) {
            Cliente::create(['nombre' => sprintf('Cliente ficticio %02d', $i), 'estado' => true]);
        }
        $entrada = $this->entradaUnidades($e, [54, 0, 0]);
        $entrada['detalles'] = [$entrada['detalles'][0]];
        $parcial = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);
        $parcial->update(['estado' => 'confirmado']);
        $parcial->refresh();
        $detalle = $parcial->detalles()->sole();
        $atender = app(AtenderPedidoUnidades::class);
        $atender->preparar($parcial, ['version_pedido' => $parcial->version, 'pedido_detalle_id' => $detalle->id,
            'cantidad' => 32, 'incidencia' => 'envases', 'motivo' => 'Ensayo ficticio: solo hay treinta y dos unidades físicamente preparadas.'], $e['usuario']);
        $parcial->refresh();
        $atender->despachar($parcial, ['version_pedido' => $parcial->version, 'operacion' => (string) Str::ulid(),
            'motivo' => 'Salida ficticia aislada de treinta y dos unidades.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 32]]], $e['usuario']);
        $parcial->refresh();
        $resumen = app(ControlEntregaPedido::class)->resumen($parcial);
        $fisica = array_values($resumen['lineas'])[0];
        app(RegistrarResultadoEntrega::class)->ejecutar($parcial, ['version' => $resumen['version'],
            'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aceptadas' => 32, 'rechazadas' => 0]],
            'observaciones' => 'Recepción ficticia de las treinta y dos unidades despachadas.'], $e['usuario']);
        $preparacion = $this->registrarUnidades($e, [24, 12, 6], $this->agrupacionesUnidades($e));
        $preparacion->update(['estado' => 'confirmado']);
        $preparacion->refresh();
        $d = $preparacion->detalles()->firstOrFail();
        $atender->preparar($preparacion, ['version_pedido' => $preparacion->version, 'pedido_detalle_id' => $d->id,
            'cantidad' => 10, 'incidencia' => 'liquido', 'motivo' => 'Comprobación ficticia: producción incompleta por falta de líquido.'], $e['usuario']);
        $impresion = $this->registrarUnidades($e, [2, 3, 1]);

        return ['parcial' => $parcial->id, 'preparacion' => $preparacion->id, 'nota1' => $parcial->numero,
            'nota2' => $impresion->numero, 'impresion' => $impresion->id, 'sku' => $e['presentacion']->id];
    }
};
file_put_contents(getenv('RUNNER_TEMP').'/visual-fixture.json', json_encode($escenario->preparar(), JSON_THROW_ON_ERROR));
echo "Datos exclusivamente ficticios preparados en SQLite temporal; MySQL bloqueado.\n";
