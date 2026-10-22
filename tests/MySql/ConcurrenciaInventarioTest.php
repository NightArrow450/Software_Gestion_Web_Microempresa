<?php

namespace Tests\MySql;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Services\TokenOperacion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-concurrencia')]
class ConcurrenciaInventarioTest extends TestCase
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
            self::$preparado = true;
        }
    }

    private function usuario(): User
    {
        $sufijo = (string) Str::ulid();
        $rol = Role::create(['nombre' => 'Prueba '.$sufijo, 'es_sistema' => false]);
        foreach (['inventario.ver', 'inventario.salida', 'inventario.entrada', 'cumplimiento.despachar', 'pedidos.ver'] as $nombre) {
            $rol->permisos()->attach(Permiso::withoutGlobalScopes()->firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }

        return User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => $sufijo.'@mysql.test', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
    }

    private function producto(int $stock): array
    {
        $usuario = $this->usuario();
        $codigo = 'C'.Str::random(18);
        $categoria = Categoria::firstOrCreate(['nombre' => 'Prueba MySQL'], ['estado' => true]);
        $producto = Producto::create(['codigo' => $codigo, 'categoria_id' => $categoria->id, 'nombre' => 'Prueba MySQL', 'estado' => true]);
        $presentacion = PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => $codigo, 'contenido' => 1, 'unidad_medida' => 'L', 'venta_por_unidad' => true, 'estado' => true]);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => $stock]);

        return [$usuario, $presentacion];
    }

    private function pedido(User $usuario, PresentacionProducto $presentacion, string $estado = 'listo_despacho'): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente MySQL', 'estado' => true]);
        $pedido = Pedido::create(['cliente_id' => $cliente->id, 'usuario_id' => $usuario->id, 'fecha_pedido' => '2026-10-22', 'fecha_entrega' => '2026-10-22', 'condicion_pago' => 'contado', 'cliente_nombre' => $cliente->nombre, 'estado' => $estado, 'total' => 2]);
        $pedido->detalles()->create(['presentacion_producto_id' => $presentacion->id, 'codigo_sku' => $presentacion->codigo_sku, 'descripcion_producto' => 'Prueba MySQL', 'modalidad' => 'unidad', 'cantidad' => 2, 'precio_unitario' => 1, 'importe' => 2]);

        return $pedido;
    }

    private function simultaneas(string $tabla, int $id, array $operaciones): array
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

    private function operacion(User $usuario, string $ruta, array $datos, ?string $token = null): array
    {
        return ['usuario_id' => $usuario->id, 'ruta' => $ruta, 'datos' => $datos + ['_token_operacion' => $token ?? TokenOperacion::emitir($usuario->id)]];
    }

    public function test_dos_pedidos_compiten_y_uno_falla_sin_saldo_negativo(): void
    {
        [$usuario, $presentacion] = $this->producto(2);
        $pedidos = [$this->pedido($usuario, $presentacion), $this->pedido($usuario, $presentacion)];
        $resultados = $this->simultaneas('presentaciones_producto', $presentacion->id, array_map(fn (Pedido $pedido): array => $this->operacion($usuario, '/cumplimiento/'.$pedido->id.'/despachar', []), $pedidos));
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(0, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
        $this->assertSame(1, MovimientoInventario::where('presentacion_producto_id', $presentacion->id)->count());
    }

    public function test_mismo_despacho_simultaneo_no_se_descuenta_dos_veces(): void
    {
        [$usuario, $presentacion] = $this->producto(10);
        $pedido = $this->pedido($usuario, $presentacion);
        $operacion = $this->operacion($usuario, '/cumplimiento/'.$pedido->id.'/despachar', []);
        $resultados = $this->simultaneas('presentaciones_producto', $presentacion->id, [$operacion, $operacion]);
        $this->assertSame([302, 302], array_column($resultados, 'estado'));
        $this->assertSame(8, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
        $this->assertSame(1, MovimientoInventario::where('pedido_id', $pedido->id)->count());
        $this->assertSame(1, $pedido->historialEstados()->count());
    }

    public function test_salidas_simultaneas_de_productos_nunca_dejan_negativos(): void
    {
        [$usuario, $presentacion] = $this->producto(10);
        $operaciones = array_map(fn (): array => $this->operacion($usuario, '/inventario/movimientos', ['presentacion_producto_id' => $presentacion->id, 'tipo' => 'salida', 'cantidad' => 7, 'motivo' => 'Salida simultánea de prueba']), [1, 2]);
        $resultados = $this->simultaneas('presentaciones_producto', $presentacion->id, $operaciones);
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(3, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
    }

    public function test_salidas_simultaneas_de_insumos_nunca_dejan_negativos(): void
    {
        $usuario = $this->usuario();
        $insumo = Insumo::create(['codigo' => 'I'.Str::ulid(), 'nombre' => 'Envase de prueba', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 10]);
        $operaciones = array_map(fn (): array => $this->operacion($usuario, '/inventario/movimientos', ['insumo_id' => $insumo->id, 'tipo' => 'salida', 'cantidad' => 7, 'motivo' => 'Salida simultánea de prueba']), [1, 2]);
        $resultados = $this->simultaneas('insumos', $insumo->id, $operaciones);
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame('3.000', $insumo->fresh()->cantidad_actual);
    }

    public function test_indices_y_check_rechazan_nulos_parciales_y_saldos_negativos(): void
    {
        [$usuario, $presentacion] = $this->producto(5);
        $datos = ['usuario_id' => $usuario->id, 'presentacion_producto_id' => $presentacion->id, 'tipo' => 'entrada', 'cantidad' => 1, 'stock_anterior' => 5, 'stock_nuevo' => 6, 'motivo' => 'Integridad MySQL', 'origen_tipo' => 'simulado', 'origen_id' => 'O'.Str::ulid()];
        MovimientoInventario::create($datos);
        MovimientoInventario::create(array_replace($datos, ['origen_id' => 'O'.Str::ulid()]));
        foreach ([$datos, array_replace($datos, ['origen_id' => null]), array_replace($datos, ['origen_tipo' => null, 'origen_id' => null])] as $invalido) {
            try {
                DB::table('movimientos_inventario')->insert($invalido);
                $this->fail('La base debió rechazar el movimiento.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
        try {
            DB::table('inventarios')->where('presentacion_producto_id', $presentacion->id)->update(['cantidad_actual' => -1]);
            $this->fail('La base debió rechazar el saldo negativo.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $this->assertSame(5, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
    }

    public function test_check_de_insumos_y_restricciones_historicas_en_mysql(): void
    {
        $insumo = Insumo::create(['codigo' => 'K'.Str::ulid(), 'nombre' => 'Envase protegido', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 5]);
        try {
            DB::table('insumos')->where('id', $insumo->id)->update(['cantidad_actual' => -1]);
            $this->fail('El CHECK debió rechazar el saldo negativo.');
        } catch (QueryException) {
            $this->assertSame('5.000', $insumo->fresh()->cantidad_actual);
        }
        [$usuario, $presentacion] = $this->producto(5);
        $pedido = $this->pedido($usuario, $presentacion);
        $antes = $pedido->detalles()->firstOrFail()->toArray();
        try {
            DB::table('presentaciones_producto')->where('id', $presentacion->id)->delete();
            $this->fail('La FK debió conservar la presentación histórica.');
        } catch (QueryException) {
            $this->assertSame($antes, $pedido->detalles()->firstOrFail()->toArray());
        }
    }

    public function test_mismo_despacho_con_tokens_distintos_sigue_protegido(): void
    {
        [$usuario, $presentacion] = $this->producto(10);
        $pedido = $this->pedido($usuario, $presentacion);
        $operaciones = array_map(fn (): array => $this->operacion($usuario, '/cumplimiento/'.$pedido->id.'/despachar', []), [1, 2]);
        $resultados = $this->simultaneas('presentaciones_producto', $presentacion->id, $operaciones);
        $estados = array_column($resultados, 'estado');
        sort($estados);
        $this->assertSame([302, 422], $estados);
        $this->assertSame(8, (int) Inventario::where('presentacion_producto_id', $presentacion->id)->value('cantidad_actual'));
        $this->assertSame(1, MovimientoInventario::where('pedido_id', $pedido->id)->count());
    }
}
