<?php

namespace Tests\MySql;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Services\GestionarPedidoUnidades;
use App\Services\LecturaPedido;
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
        $e = $this->escenarioEnvasado();
        $e['usuario']->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => 'pedidos.crear'])->id);
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
        $cliente = Cliente::create(['nombre' => 'Cliente ficticio posterior al retiro', 'estado' => true]);
        $pedido = app(GestionarPedidoUnidades::class)->guardar(['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(),
            'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 7]]], $e['usuario']);
        $this->assertSame('14.00', $pedido->total);
        $this->assertSame(7, (int) $pedido->detalles()->sole()->cantidad);
        foreach (['users', 'roles', 'permisos', 'rol_permiso'] as $tabla) {
            $this->assertEquals($originales[$tabla][1], DB::table($tabla)->get()->toArray(), $tabla);
        }
    }
}
