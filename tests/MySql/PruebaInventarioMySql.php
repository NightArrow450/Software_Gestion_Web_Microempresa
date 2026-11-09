<?php

namespace Tests\MySql;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Services\LecturaPedido;
use App\Services\TokenOperacion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class PruebaInventarioMySql extends TestCase
{
    private static bool $preparado = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MYSQL_TEST_ENABLE') !== '1') {
            $this->markTestSkipped('PENDIENTE DE EJECUCIÓN: habilita MySQL de pruebas con MYSQL_TEST_ENABLE=1.');
        }
        $conexion = EntornoMySql::proteger();
        $servidor = new \PDO('mysql:host='.$conexion['host'].';port='.$conexion['port'].';charset=utf8mb4', $conexion['username'], $conexion['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $nombre = $conexion['database'];
        $servidor->exec("CREATE DATABASE IF NOT EXISTS `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        EntornoMySql::conectar();
        if (! self::$preparado) {
            $tablas = Schema::getTableListing($nombre);
            if ($tablas !== [] && (! Schema::hasTable('__dhacer_pruebas') || ! DB::table('__dhacer_pruebas')->where('propietario', 'DHACER_ECO_LIMP_CONCURRENCIA')->exists())) {
                throw new \RuntimeException('La base ya contiene tablas ajenas a estas pruebas. No se modificará.');
            }
            DB::statement('CREATE TABLE IF NOT EXISTS __dhacer_pruebas (propietario VARCHAR(100) PRIMARY KEY)');
            DB::table('__dhacer_pruebas')->insertOrIgnore(['propietario' => 'DHACER_ECO_LIMP_CONCURRENCIA']);
            if (! Schema::hasTable('migrations')) {
                $anteriores = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $ruta): bool => basename($ruta) < '2026_10_22_163921'));
                Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--path' => $anteriores, '--realpath' => true, '--force' => true]);
                [$usuario, $presentacion] = $this->producto(8);
                $pedido = $this->pedido($usuario, $presentacion, 'despachado');
                DB::table('movimientos_inventario')->insert(['presentacion_producto_id' => $presentacion->id, 'usuario_id' => $usuario->id, 'tipo' => 'entrada', 'cantidad' => 5, 'stock_anterior' => 5, 'stock_nuevo' => 10, 'motivo' => 'Entrada histórica manual', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('movimientos_inventario')->insert(['pedido_id' => $pedido->id, 'presentacion_producto_id' => $presentacion->id, 'usuario_id' => $usuario->id, 'tipo' => 'salida', 'cantidad' => 2, 'stock_anterior' => 10, 'stock_nuevo' => 8, 'motivo' => 'Despacho histórico de prueba', 'created_at' => now(), 'updated_at' => now()]);
            }
            $instantaneas = [];
            foreach (['users', 'clientes', 'productos', 'presentaciones_producto', 'inventarios', 'insumos', 'pedidos', 'pedido_detalles', 'pedido_estados_historial', 'movimientos_inventario'] as $tabla) {
                $columnas = Schema::getColumnListing($tabla);
                $instantaneas[$tabla] = [$columnas, DB::table($tabla)->select($columnas)->orderBy('id')->get()->toJson()];
            }
            Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--force' => true]);
            foreach ($instantaneas as $tabla => [$columnas, $contenido]) {
                $this->assertSame($contenido, DB::table($tabla)->select($columnas)->orderBy('id')->get()->toJson(), 'La migración alteró datos existentes de '.$tabla);
            }
            app()->forgetInstance(LecturaPedido::class);
            self::$preparado = true;
        }
    }

    protected function usuario(): User
    {
        $sufijo = (string) Str::ulid();
        $rol = Role::create(['nombre' => 'Prueba '.$sufijo, 'es_sistema' => false]);
        foreach (['inventario.ver', 'inventario.salida', 'inventario.entrada', 'cumplimiento.despachar', 'pedidos.ver'] as $nombre) {
            $rol->permisos()->attach(Permiso::withoutGlobalScopes()->firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }

        return User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => $sufijo.'@mysql.test', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
    }

    protected function producto(int $stock): array
    {
        $usuario = $this->usuario();
        $codigo = 'C'.Str::random(18);
        $categoria = Categoria::firstOrCreate(['nombre' => 'Prueba MySQL'], ['estado' => true]);
        $producto = Producto::create(['codigo' => $codigo, 'categoria_id' => $categoria->id, 'nombre' => 'Prueba MySQL', 'estado' => true]);
        $presentacion = PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => $codigo, 'contenido' => 1, 'unidad_medida' => 'L', 'venta_por_unidad' => true, 'estado' => true]);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => $stock]);

        return [$usuario, $presentacion];
    }

    protected function pedido(User $usuario, PresentacionProducto $presentacion, string $estado = 'listo_despacho'): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente MySQL', 'estado' => true]);
        $pedido = Pedido::create(['cliente_id' => $cliente->id, 'usuario_id' => $usuario->id, 'fecha_pedido' => '2026-10-22', 'fecha_entrega' => '2026-10-22', 'condicion_pago' => 'contado', 'cliente_nombre' => $cliente->nombre, 'estado' => $estado, 'total' => 2]);
        $pedido->detalles()->create(['presentacion_producto_id' => $presentacion->id, 'codigo_sku' => $presentacion->codigo_sku, 'descripcion_producto' => 'Prueba MySQL', 'modalidad' => 'unidad', 'cantidad' => 2, 'precio_unitario' => 1, 'importe' => 2]);

        return $pedido;
    }

    protected function simultaneas(string $tabla, int $id, array $operaciones): array
    {
        config(['database.connections.mysql_bloqueo' => config('database.connections.mysql_concurrencia')]);
        DB::purge('mysql_bloqueo');
        $bloqueo = DB::connection('mysql_bloqueo');
        $procesos = [];
        $bloqueo->beginTransaction();
        try {
            $bloqueo->table($tabla)->where('id', $id)->lockForUpdate()->first();
            $conexiones = [];
            foreach ($operaciones as $operacion) {
                $proceso = proc_open([PHP_BINARY, base_path('tests/MySql/operacion.php')], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $tuberias, base_path());
                $this->assertIsResource($proceso);
                $procesos[] = [$proceso, $tuberias];
                fwrite($tuberias[0], json_encode($operacion, JSON_THROW_ON_ERROR));
                fclose($tuberias[0]);
                stream_set_timeout($tuberias[1], 20);
                do {
                    $linea = fgets($tuberias[1]);
                } while ($linea !== false && trim($linea) === '');
                $this->assertMatchesRegularExpression('/^LISTO:[0-9]+/', (string) $linea);
                $conexiones[] = (int) substr($linea, 6);
            }
            $limite = microtime(true) + 10;
            do {
                $esperando = DB::selectOne('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) AS total FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID = w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN (?, ?)', $conexiones)->total;
                if ((int) $esperando === 2) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $limite);

        } catch (\Throwable $exception) {
            foreach ($procesos as [$proceso]) {
                if (is_resource($proceso)) {
                    proc_terminate($proceso);
                    proc_close($proceso);
                }
            }
            throw $exception;
        } finally {
            $bloqueo->rollBack();
        }
        $resultados = [];
        try {
            foreach ($procesos as [$proceso, $tuberias]) {
                $salida = stream_get_contents($tuberias[1]);
                $errores = stream_get_contents($tuberias[2]);
                fclose($tuberias[1]);
                fclose($tuberias[2]);
                $this->assertSame(0, proc_close($proceso), $errores);
                $resultados[] = json_decode(trim($salida), true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            foreach ($procesos as [$proceso]) {
                if (is_resource($proceso)) {
                    proc_terminate($proceso);
                    proc_close($proceso);
                }
            }
        }

        $this->assertSame(2, (int) $esperando, 'Ambos procesos deben solaparse: '.json_encode(['conexiones' => $conexiones, 'respuestas' => array_column($resultados, 'estado')]));

        return $resultados;
    }

    protected function operacion(User $usuario, string $ruta, array $datos, ?string $token = null): array
    {
        return ['usuario_id' => $usuario->id, 'ruta' => $ruta, 'control_materiales_exacto' => (bool) config('envasado.control_materiales_exacto', false), 'datos' => $datos + ['_token_operacion' => $token ?? TokenOperacion::emitir($usuario->id)]];
    }
}
