<?php

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Services\ConfigurarMaterialesEnvasado;
use Database\Seeders\EntregaRetornoPermisoSeeder;
use Database\Seeders\EnvasadoPermisoSeeder;
use Database\Seeders\IndicadoresPermisoSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$entorno = require __DIR__.'/entorno_redisenio.php';
if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Solo CLI.');
}
if (($argv[1] ?? '') === 'preparar') {
    if (DB::selectOne('SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE()')->n !== 0) {
        throw new RuntimeException('La base visual debe estar vacía. No se limpia ni se reutiliza automáticamente.');
    }
    if (Artisan::call('migrate', ['--database' => 'mysql_concurrencia', '--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('Falló la migración aislada; no se reintenta.');
    }
    foreach ([RoleSeeder::class, PermisoSeeder::class, RolPermisoSeeder::class,
        EnvasadoPermisoSeeder::class, EntregaRetornoPermisoSeeder::class,
        IndicadoresPermisoSeeder::class] as $seeder) {
        (new $seeder)->run();
    }
    foreach (['Administrador' => 'admin', 'Operaciones Comerciales' => 'operaciones', 'Producción/Reparto' => 'reparto'] as $rol => $alias) {
        User::factory()->create(['first_name' => 'Usuario', 'last_name' => 'Ficticio', 'email' => $alias.'@pr44.test',
            'password' => $entorno['privadas']['clave_navegador'], 'role_id' => Role::where('nombre', $rol)->sole()->id,
            'status' => true, 'debe_cambiar_password' => false]);
    }
    $categoria = Categoria::create(['nombre' => 'Datos ficticios PR44', 'estado' => true]);
    $cliente = Cliente::create(['nombre' => 'Cliente ficticio PR44', 'estado' => true]);
    foreach (['LIM' => ['Limpiatodo ficticio', 1, 2, ['Lavanda', 'Frutos', 'BB', 'Manzana', 'Limón']],
        'QGR' => ['Quitagrasa ficticio', 3.8, 6, [null]], 'VID' => ['Limpiavidrios ficticio', 1, 3, [null]],
        'HIS' => ['Producto histórico ficticio', 1, 3, [null]]] as $codigo => [$nombre, $contenido, $precio, $aromas]) {
        $producto = Producto::create(['codigo' => $codigo, 'nombre' => $nombre, 'categoria_id' => $categoria->id, 'estado' => true]);
        foreach ($aromas as $i => $aroma) {
            $variante = $aroma === null ? null : VarianteProducto::create(['producto_id' => $producto->id, 'tipo' => 'Aroma', 'valor' => $aroma, 'estado' => true]);
            $sku = PresentacionProducto::create(['producto_id' => $producto->id, 'variante_producto_id' => $variante?->id,
                'codigo_sku' => $codigo.'-'.($i + 1), 'contenido' => $contenido, 'unidad_medida' => 'L', 'tipo_envase' => $codigo === 'QGR' ? 'Galón' : 'Botella',
                'venta_por_unidad' => true, 'venta_por_empaque' => true, 'unidades_por_empaque' => 12,
                'precio_unitario' => $precio, 'precio_empaque' => $precio * 12, 'estado' => true]);
            Inventario::create(['presentacion_producto_id' => $sku->id, 'cantidad_actual' => match ($codigo) {
                'QGR' => 0, 'HIS' => 60, default => 100
            }]);
            if ($codigo === 'QGR') {
                $materiales = [];
                foreach (['envase', 'etiqueta', 'contratapa'] as $funcion) {
                    $insumo = Insumo::create(['codigo' => strtoupper($funcion), 'nombre' => ucfirst($funcion).' ficticio', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 200, 'estado' => true]);
                    $materiales[] = ['insumo_id' => $insumo->id, 'funcion' => $funcion, 'base_consumo' => 'unidad', 'cantidad_por_unidad' => 1];
                }
                app(ConfigurarMaterialesEnvasado::class)->guardar($sku, ['nombre' => 'Configuración ficticia PR44', 'estado' => true, 'version' => 0, 'materiales' => $materiales]);
            }
            if ($codigo === 'HIS') {
                $pedido = Pedido::create(['cliente_id' => $cliente->id, 'usuario_id' => User::where('email', 'operaciones@pr44.test')->sole()->id,
                    'cliente_nombre' => $cliente->nombre, 'estado' => 'confirmado', 'fecha_pedido' => '2026-10-01', 'fecha_entrega' => today(), 'condicion_pago' => 'contado', 'total' => 48]);
                $pedido->detalles()->create(['presentacion_producto_id' => $sku->id, 'codigo_sku' => $sku->codigo_sku,
                    'descripcion_producto' => $nombre, 'modalidad' => 'empaque', 'unidades_por_empaque' => 12, 'cantidad' => 2, 'precio_unitario' => 24, 'importe' => 48]);
            }
        }
    }
    Insumo::create(['codigo' => 'MANGA', 'nombre' => 'Manga ficticia sin consumo configurado', 'categoria' => 'Otros', 'unidad_medida' => 'metro', 'cantidad_actual' => 100, 'estado' => true]);
}
$datos = ['base' => $entorno['identidad']->base, 'uuid' => $entorno['identidad']->uuid,
    'inventarios' => DB::table('inventarios as i')->join('presentaciones_producto as p', 'p.id', '=', 'i.presentacion_producto_id')->pluck('i.cantidad_actual', 'p.codigo_sku'),
    'materiales' => DB::table('insumos')->pluck('cantidad_actual', 'codigo'),
    'pedidos' => DB::table('pedidos')->get(['id', 'estado', 'total', 'modelo_comercial']),
    'movimientos' => DB::table('movimientos_inventario')->count(), 'migraciones' => DB::table('migrations')->count(),
    'tablas' => (int) DB::selectOne('SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE()')->n];
echo json_encode($datos, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
