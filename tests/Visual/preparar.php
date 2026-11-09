<?php

use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use App\Services\AtenderPedidoUnidades;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarPedidoUnidades;
use App\Services\RegistrarEnvasado;
use App\Services\RegistrarResultadoEntrega;
use App\Services\RegistrarRetornoPedido;
use Database\Seeders\AuditoriaPermisoSeeder;
use Database\Seeders\EntregaRetornoPermisoSeeder;
use Database\Seeders\EnvasadoPermisoSeeder;
use Database\Seeders\IndicadoresPermisoSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RolPermisoSeeder;
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
        foreach ([RoleSeeder::class, PermisoSeeder::class, RolPermisoSeeder::class, EntregaRetornoPermisoSeeder::class,
            IndicadoresPermisoSeeder::class, AuditoriaPermisoSeeder::class, EnvasadoPermisoSeeder::class] as $clase) {
            (new $clase)->run();
        }
        $rol = Role::where('nombre', 'Administrador')->sole();
        $rol->permisos()->syncWithoutDetaching(Permiso::pluck('id')->all());
        $e['usuario']->update(['email' => 'visual@example.test', 'first_name' => 'Ana', 'last_name' => 'Demostración',
            'password' => getenv('VISUAL_PASSWORD'), 'debe_cambiar_password' => false, 'role_id' => $rol->id]);
        $e['usuario']->unsetRelation('role');
        foreach (['Operaciones Comerciales' => 'comercial.visual@example.test', 'Producción/Reparto' => 'produccion.visual@example.test'] as $nombre => $correo) {
            User::create(['first_name' => 'Usuario', 'last_name' => 'Ficticio', 'email' => $correo,
                'password' => getenv('VISUAL_PASSWORD'), 'debe_cambiar_password' => false, 'status' => true, 'role_id' => Role::where('nombre', $nombre)->sole()->id]);
        }

        $e['presentacion']->producto->update(['nombre' => 'Quitásarro ficticio', 'codigo' => 'VISUAL-QTS']);
        foreach ($e['skus'] as $indice => $sku) {
            $sku->update(['codigo_sku' => 'VISUAL-QTS-'.str_repeat('W', 20).'-'.$indice]);
        }
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
        $d = $preparacion->detalles()->where('presentacion_producto_id', $e['skus'][1]->id)->sole();
        $atender->preparar($preparacion, ['version_pedido' => $preparacion->version, 'pedido_detalle_id' => $d->id,
            'cantidad' => 10, 'incidencia' => 'liquido', 'motivo' => 'Comprobación ficticia: producción incompleta por falta de líquido.'], $e['usuario']);
        $impresion = $this->registrarUnidades($e, [2, 3, 1]);

        $preparacion->update(['cliente_nombre' => 'JOSÉ NÚÑEZ — pedido antiguo ficticio']);
        for ($i = 0; $i < 25; $i++) {
            $extra = app(GestionarPedidoUnidades::class)->guardar($entrada, $e['usuario']);
            $extra->update(['estado' => 'confirmado']);
        }

        $entradaRechazo = $this->entradaUnidades($e, [0, 0, 4]);
        $entradaRechazo['detalles'] = [$entradaRechazo['detalles'][2]];
        $rechazo = app(GestionarPedidoUnidades::class)->guardar($entradaRechazo, $e['usuario']);
        $this->despacharUnidades($rechazo, $e);
        $rechazo->refresh();
        $resultado = app(ControlEntregaPedido::class)->resumen($rechazo);
        $referencia = array_values($resultado['lineas'])[0];
        app(RegistrarResultadoEntrega::class)->ejecutar($rechazo, ['version' => $resultado['version'],
            'detalles' => [['pedido_detalle_id' => $referencia['id'], 'aceptadas' => 1, 'rechazadas' => 2,
                'motivo' => 'Dos unidades ficticias rechazadas para revisar la presentación de retornos.']],
            'observaciones' => 'Escenario visual con una unidad aún pendiente de resolución.'], $e['usuario']);
        $rechazo->refresh();
        $resultado = app(ControlEntregaPedido::class)->resumen($rechazo);
        app(RegistrarRetornoPedido::class)->ejecutar($rechazo, ['version' => $resultado['version'],
            'observaciones' => 'Recepción ficticia de una unidad apta; no se aprueba ni aumenta stock.',
            'detalles' => [['pedido_detalle_id' => $referencia['id'], 'aptas' => 1, 'danadas' => 0,
                'en_revision' => 0, 'no_recuperadas' => 0]]], $e['usuario']);
        $envasado = app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $e['skus'][2]->id,
            'cantidad' => 3, 'observaciones' => 'Producción anticipada exclusivamente ficticia para revisión visual.'], $e['usuario']);

        return ['parcial' => $parcial->id, 'preparacion' => $preparacion->id, 'nota1' => $parcial->numero,
            'nota2' => $impresion->numero, 'impresion' => $impresion->id, 'sku' => $e['presentacion']->id, 'producto' => $e['presentacion']->producto_id,
            'cliente' => $parcial->cliente_id, 'rechazo' => $rechazo->id, 'envasado' => $envasado->id];
    }
};
file_put_contents(getenv('RUNNER_TEMP').'/visual-fixture.json', json_encode($escenario->preparar(), JSON_THROW_ON_ERROR));
echo "Datos exclusivamente ficticios preparados en SQLite temporal; MySQL bloqueado.\n";
