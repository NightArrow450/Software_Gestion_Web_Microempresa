<?php

namespace Tests\MySql;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Services\AprobarRetornoPedido;
use App\Services\AtenderPedidoUnidades;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarPedidoUnidades;
use App\Services\LecturaPedido;
use App\Services\RegistrarResultadoEntrega;
use App\Services\RegistrarRetornoPedido;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\EnvasadoEscenario;

class RetiroSurtidosMySqlTest extends PruebaInventarioMySql
{
    use EnvasadoEscenario;

    public function test_retiro_mysql_aislado_conserva_cuentas_y_permite_unidades_sin_seis_tablas(): void
    {
        $conexion = EntornoMySql::proteger();
        $conexion['database'] .= '_retiro';
        $nombre = $conexion['database'];
        $this->assertLessThanOrEqual(64, strlen($nombre));
        config(['database.connections.mysql_concurrencia' => $conexion]);
        EntornoMySql::proteger();
        $servidor = new PDO('mysql:host='.$conexion['host'].';port='.$conexion['port'].';charset=utf8mb4', $conexion['username'], $conexion['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $servidor->exec("CREATE DATABASE IF NOT EXISTS `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        EntornoMySql::conectar();
        $tablasIniciales = Schema::getTableListing($nombre, false);
        $this->assertSame([], $tablasIniciales, 'Solo se ensaya en una base independiente nueva y vacía.');
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--force' => true, '--no-interaction' => true]));
        app()->forgetInstance(LecturaPedido::class);
        $e = $this->escenarioEnvasado(100, 100);
        foreach (['pedidos.crear', 'cumplimiento.preparar', 'cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar'] as $permiso) {
            $e['usuario']->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => $permiso])->id);
        }
        $e['usuario']->unsetRelation('role');
        $originales = [];
        foreach (Schema::getTableListing($nombre, false) as $tabla) {
            if ($tabla !== 'migrations') {
                $columnas = Schema::getColumnListing($tabla);
                if ($tabla === 'pedido_detalles') {
                    $columnas = array_values(array_diff($columnas, ['formato_surtido_id']));
                }
                $originales[$tabla] = [$columnas, DB::table($tabla)->select($columnas)->get()->toArray()];
            }
        }
        putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS');
        try {
            DB::unprepared('CREATE TRIGGER dependencia_sql_prueba BEFORE UPDATE ON clientes FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM formatos_surtidos) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Dependencia ficticia\'; END IF; END');
            try {
                (require database_path('migrations/retiro_controlado/2026_10_30_003639_retirar_estructuras_surtidos_vacias.php'))->up();
                $this->fail('El trigger externo debe bloquear el retiro antes del primer DDL.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('No se ha iniciado DDL', $error->getMessage());
            }
            $this->assertTrue(Schema::hasTable('formatos_surtidos'));
            $this->assertTrue(Schema::hasColumn('pedido_detalles', 'formato_surtido_id'));
            DB::unprepared('DROP TRIGGER dependencia_sql_prueba');
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--path' => ['database/migrations/retiro_controlado/2026_10_30_003639_retirar_estructuras_surtidos_vacias.php'], '--force' => true, '--no-interaction' => true]));
        } finally {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
        }
        $retiradas = ['formatos_surtidos', 'formato_surtido_skus', 'pedido_paquetes_surtidos', 'paquete_surtido_composiciones', 'paquete_surtido_componentes', 'pedido_despacho_componentes'];
        $this->assertDatabaseCount('migrations', 49);
        $this->assertCount(46, Schema::getTableListing($nombre, false));
        foreach ($originales as $tabla => [$columnas, $filas]) {
            if (in_array($tabla, $retiradas, true)) {
                $this->assertFalse(Schema::hasTable($tabla));
                $this->assertSame([], $filas);
            } else {
                $this->assertEquals($filas, DB::table($tabla)->select($columnas)->get()->toArray(), $tabla);
                foreach (Schema::getForeignKeys($tabla) as $foreign) {
                    $this->assertNotContains($foreign['foreign_table'], $retiradas);
                }
            }
        }
        $triggers = DB::table('information_schema.triggers')->where('TRIGGER_SCHEMA', $nombre)->pluck('ACTION_STATEMENT');
        foreach ($triggers as $definicion) {
            $this->assertDoesNotMatchRegularExpression('/formatos_surtidos|formato_surtido_skus|pedido_paquetes_surtidos|paquete_surtido_composiciones|paquete_surtido_componentes|pedido_despacho_componentes/i', $definicion);
        }
        $uuid = DB::selectOne('SELECT @@server_uuid uuid')->uuid;
        $this->assertSame(0, Artisan::call('comercio:preparar-saneamiento', ['--base' => $nombre, '--uuid' => $uuid]));
        $plan = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('SOLO_LECTURA_NO_EJECUTA_LIMPIEZA', $plan['modo']);
        $this->assertCount(46, $plan['tablas']);
        $cliente = Cliente::create(['nombre' => 'Cliente ficticio posterior al retiro', 'estado' => true]);
        $pedido = app(GestionarPedidoUnidades::class)->guardar(['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(),
            'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 7]]], $e['usuario']);
        $this->assertSame('14.00', $pedido->total);
        $this->assertSame(7, (int) $pedido->detalles()->sole()->cantidad);
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        $detalle = $pedido->detalles()->sole();
        $atencion = app(AtenderPedidoUnidades::class);
        $atencion->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => 7,
            'motivo' => 'Siete unidades verificadas en el ensayo sin tablas retiradas.'], $e['usuario']);
        $pedido->refresh();
        $salida = ['version_pedido' => $pedido->version, 'operacion' => 'retiro-mysql-salida-unica', 'motivo' => 'Salida física de siete unidades para el ensayo aislado.', 'detalles' => [['id' => $detalle->id, 'cantidad' => 7]]];
        $id = $atencion->despachar($pedido, $salida, $e['usuario']);
        $this->assertSame($id, $atencion->despachar($pedido, $salida, $e['usuario']));
        $control = app(ControlEntregaPedido::class);
        $pedido->refresh();
        $fisica = array_values($control->resumen($pedido)['lineas'])[0];
        app(RegistrarResultadoEntrega::class)->ejecutar($pedido, ['version' => $control->resumen($pedido)['version'],
            'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aceptadas' => 5, 'rechazadas' => 2, 'motivo' => 'Dos unidades rechazadas ficticias para verificar el retorno.']]], $e['usuario']);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 93]);
        app(RegistrarRetornoPedido::class)->ejecutar($pedido->fresh(), ['version' => $control->resumen($pedido)['version'], 'observaciones' => 'Se reciben una unidad apta y otra dañada físicamente.',
            'detalles' => [['pedido_detalle_id' => $fisica['id'], 'aptas' => 1, 'danadas' => 1, 'en_revision' => 0, 'no_recuperadas' => 0]]], $e['usuario']);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 93]);
        $retorno = DB::table('retorno_pedido_detalles')->sole();
        app(AprobarRetornoPedido::class)->ejecutar($pedido->fresh(), ['version' => $control->resumen($pedido)['version'], 'retorno_detalle_id' => $retorno->id,
            'cantidad' => 1, 'motivo' => 'Se aprueba exclusivamente la unidad recuperada y apta.'], $e['usuario']);
        $this->assertDatabaseHas('inventarios', ['presentacion_producto_id' => $e['presentacion']->id, 'cantidad_actual' => 94]);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        foreach (['users', 'roles', 'permisos', 'rol_permiso'] as $tabla) {
            $this->assertEquals($originales[$tabla][1], DB::table($tabla)->get()->toArray(), $tabla);
        }
    }
}
