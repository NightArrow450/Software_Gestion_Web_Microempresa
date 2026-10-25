<?php

namespace Database\Seeders;

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
use App\Services\AprobarRetornoPedido;
use App\Services\ConfigurarFormatoSurtido;
use App\Services\ConfigurarMaterialesEnvasado;
use App\Services\ControlEntregaPedido;
use App\Services\GestionarSurtidos;
use App\Services\RegistrarEnvasado;
use App\Services\RegistrarResultadoEntrega;
use App\Services\RegistrarRetornoPedido;
use App\Services\TransicionesPedido;
use App\Services\VerificadorInventario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Performance\AutorizacionRendimiento;

class CargaSeeder extends Seeder
{
    public function run(): void
    {
        $m = (new AutorizacionRendimiento)->verificar('cargar');
        $clave = (string) getenv('CARGA_PASSWORD');
        if (! preg_match('/\A(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{10,100}\z/', $clave)) {
            throw new RuntimeException('Falta contraseña ficticia privada válida para las cuentas de carga.');
        }
        $tablas = Schema::getTableListing(DB::getDatabaseName(), false);
        if (count($tablas) !== 44 || DB::table('migrations')->count() !== 45) {
            throw new RuntimeException('El esquema no coincide con las 45 migraciones y 44 tablas autorizadas.');
        }
        foreach ($tablas as $tabla) {
            if ($tabla !== 'migrations' && DB::table($tabla)->exists()) {
                if ($tabla === 'permisos') {
                    AutorizacionRendimiento::validarPermisosIniciales(DB::table($tabla)->get()->map(static fn ($fila): array => (array) $fila)->all());

                    continue;
                }
                throw new RuntimeException('Carga bloqueada: la base debe contener solo el esquema vacío autorizado.');
            }
        }
        $marca = dirname((string) realpath((string) getenv('RENDIMIENTO_MANIFIESTO'))).'/'.$m['base'].'.carga-iniciada';
        $archivo = @fopen($marca, 'x');
        if ($archivo === false) {
            throw new RuntimeException('Ya existe un intento de carga; no se reintentará ni borrará nada automáticamente.');
        }
        fwrite($archivo, json_encode(['autorizacion' => $m['autorizacion'], 'estado' => 'iniciada'], JSON_THROW_ON_ERROR));
        fclose($archivo);
        $this->call([RoleSeeder::class, PermisoSeeder::class, RolPermisoSeeder::class, AuditoriaPermisoSeeder::class,
            EnvasadoPermisoSeeder::class, EntregaRetornoPermisoSeeder::class, SurtidosPermisoSeeder::class, IndicadoresPermisoSeeder::class]);
        $roles = Role::whereIn('nombre', ['Administrador', 'Operaciones Comerciales', 'Producción/Reparto'])->pluck('id', 'nombre');
        $administrador = User::create(['first_name' => 'Administración', 'last_name' => 'Sintética', 'email' => 'admin@carga.invalid', 'password' => $clave, 'status' => true, 'debe_cambiar_password' => false, 'role_id' => $roles['Administrador']]);
        Auth::login($administrador);
        try {
            for ($n = 1; $n <= 50; $n++) {
                User::create(['first_name' => 'Operador', 'last_name' => 'Sintético', 'email' => 'vu'.$n.'@carga.invalid', 'password' => $clave, 'status' => true, 'debe_cambiar_password' => false, 'role_id' => $roles['Operaciones Comerciales']]);
            }
            User::create(['first_name' => 'Producción', 'last_name' => 'Sintética', 'email' => 'produccion@carga.invalid', 'password' => $clave, 'status' => true, 'debe_cambiar_password' => false, 'role_id' => $roles['Producción/Reparto']]);
            $clientes = [];
            for ($n = 1; $n <= 300; $n++) {
                $clientes[] = Cliente::create(['nombre' => 'Cliente sintético '.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'estado' => true]);
            }
            $categoria = Categoria::create(['nombre' => 'Carga sintética', 'estado' => true]);
            $materiales = [];
            foreach (['Envase', 'Etiqueta', 'Contratapa'] as $n => $nombre) {
                $materiales[] = Insumo::create(['codigo' => 'CARGA_M'.$n, 'nombre' => $nombre.' sintético', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => 100000, 'estado' => true]);
            }
            $skus = [];
            $configuraciones = [];
            $formatos = [];
            for ($n = 0; $n < 150; $n++) {
                $producto = Producto::create(['codigo' => 'CARGA_P'.$n, 'nombre' => 'Producto sintético '.$n, 'categoria_id' => $categoria->id, 'estado' => true]);
                $grupo = [];
                foreach (['Alfa', 'Beta', 'Gamma'] as $aroma) {
                    $variante = VarianteProducto::create(['producto_id' => $producto->id, 'tipo' => 'Aroma', 'valor' => $aroma, 'estado' => true]);
                    $sku = PresentacionProducto::create(['producto_id' => $producto->id, 'variante_producto_id' => $variante->id, 'codigo_sku' => 'CARGA_'.$n.'_'.strtoupper($aroma), 'contenido' => 1, 'unidad_medida' => 'L', 'tipo_envase' => 'Botella', 'unidades_por_empaque' => 12, 'venta_por_unidad' => true, 'venta_por_empaque' => true, 'precio_unitario' => 2, 'precio_empaque' => 24, 'estado' => true]);
                    Inventario::create(['presentacion_producto_id' => $sku->id, 'cantidad_actual' => 10000]);
                    $grupo[] = $sku;
                }
                $skus[] = $grupo;
                $config = app(ConfigurarMaterialesEnvasado::class)->guardar($grupo[0], ['nombre' => 'Alternativa sintética', 'estado' => true, 'version' => 0, 'materiales' => array_map(static fn ($insumo, $funcion): array => ['insumo_id' => $insumo->id, 'funcion' => $funcion, 'base_consumo' => 'unidad', 'cantidad_por_unidad' => 1], $materiales, ['envase', 'etiqueta', 'contratapa'])]);
                $configuraciones[] = $config;
                $formatos[] = app(ConfigurarFormatoSurtido::class)->guardar(['producto_id' => $producto->id, 'nombre' => 'Surtido sintético de doce', 'unidades_por_empaque' => 12, 'precio_empaque' => '24.00', 'estado' => true, 'skus' => array_column($grupo, 'id')], $administrador);
                app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $grupo[0]->id, 'configuracion_envasado_id' => $config->id, 'version_configuracion' => 0, 'cantidad' => 10, 'observaciones' => 'Envasado anticipado exclusivamente sintético.'], $administrador);
            }
            $transiciones = app(TransicionesPedido::class);
            for ($n = 0; $n < 3000; $n++) {
                if ($n % 100 === 0) {
                    (new AutorizacionRendimiento)->verificar('cargar');
                }
                $indice = $n % 150;
                $cliente = $clientes[$n % 300];
                $sku = $skus[$indice][0];
                $surtido = $n % 5 === 0;
                $empaque = $surtido || $n % 4 === 0;
                $factor = $surtido ? 12 : ($n % 8 === 0 ? 15 : 12);
                $precio = $empaque ? $factor * 2 : 2;
                $cantidad = $empaque ? 2 : 5;
                $pedido = Pedido::create(['cliente_id' => $cliente->id, 'usuario_id' => $administrador->id, 'cliente_nombre' => $cliente->nombre, 'estado' => 'registrado', 'fecha_pedido' => today()->subDays($n % 30), 'fecha_entrega' => today()->addDays($n % 15), 'condicion_pago' => 'contado', 'total' => $cantidad * $precio, 'observaciones' => 'Datos sintéticos de rendimiento, sin valor comercial.']);
                $pedido->historialEstados()->create(['usuario_id' => $administrador->id, 'estado_nuevo' => 'registrado', 'motivo' => 'Registro sintético autorizado.']);
                $detalle = $pedido->detalles()->create(['presentacion_producto_id' => $sku->id, 'codigo_sku' => $sku->codigo_sku, 'descripcion_producto' => 'Presentación sintética', 'tipo_linea' => $surtido ? 'surtido' : 'normal', 'formato_surtido_id' => $surtido ? $formatos[$indice] : null, 'modalidad' => $empaque ? 'empaque' : 'unidad', 'unidades_por_empaque' => $empaque ? $factor : null, 'cantidad' => $cantidad, 'precio_unitario' => $precio, 'importe' => $cantidad * $precio]);
                if ($surtido) {
                    $paquetes = array_map(fn (array $cantidades): array => ['estado' => 'definida', 'componentes' => array_map(fn (int $i): array => ['presentacion_producto_id' => $skus[$indice][$i]->id, 'cantidad' => $cantidades[$i]], [0, 1, 2])], [[5, 4, 3], [2, 7, 3]]);
                    if ($n % 20 === 0) {
                        $paquetes[1] = ['estado' => 'pendiente', 'componentes' => []];
                    }
                    app(GestionarSurtidos::class)->guardar($detalle, $paquetes, $administrador, 'Composición exclusivamente sintética de carga.');
                }
                $etapa = $surtido ? ($n % 20 === 0 ? 2 : 3) : $n % 10;
                if ($surtido && $n % 50 === 5) {
                    $etapa = 9;
                }
                if ($etapa === 0) {
                    continue;
                }
                if ($etapa === 1) {
                    $transiciones->registrar($pedido, 'registrado', 'cancelado', $administrador->id, [], 'Cancelación sintética de carga.');

                    continue;
                }
                $transiciones->registrar($pedido, 'registrado', 'confirmado', $administrador->id, [], 'Confirmación sintética de carga.');
                if ($n % 100 === 2) {
                    app(RegistrarEnvasado::class)->ejecutar(['presentacion_producto_id' => $sku->id, 'configuracion_envasado_id' => $configuraciones[$indice]->id, 'version_configuracion' => 0, 'pedido_id' => $pedido->id, 'cantidad' => 3], $administrador);
                }
                foreach (['en_preparacion', 'listo_despacho', 'despachado'] as $paso => $estado) {
                    if ($etapa < $paso + 3) {
                        break;
                    }
                    $transiciones->registrar($pedido, null, $estado, $administrador->id, [], 'Transición sintética de carga.');
                }
                if ($etapa >= 6) {
                    $lineas = app(ControlEntregaPedido::class)->fotografia($pedido->fresh());
                    $resultados = array_map(static fn (array $linea): array => ['pedido_detalle_id' => $linea['id'], 'despacho_componente_id' => $linea['componente_id'] ?? null, 'aceptadas' => $linea['despachadas'] - ($etapa === 6 ? 0 : 2), 'rechazadas' => $etapa === 6 ? 0 : 2, 'motivo' => 'Resultado exclusivamente sintético.'], array_values($lineas));
                    app(RegistrarResultadoEntrega::class)->ejecutar($pedido, ['version' => 0, 'detalles' => $resultados], $administrador);
                }
                if ($etapa >= 8) {
                    $primera = array_values($lineas)[0];
                    app(RegistrarRetornoPedido::class)->ejecutar($pedido, ['version' => 1, 'observaciones' => 'Retorno exclusivamente sintético.', 'detalles' => [['pedido_detalle_id' => $detalle->id, 'despacho_componente_id' => $primera['componente_id'] ?? null, 'aptas' => 1, 'danadas' => 1, 'en_revision' => 0, 'no_recuperadas' => 0]]], $administrador);
                }
                if ($etapa === 9) {
                    $retorno = DB::table('retorno_pedido_detalles')->where('pedido_detalle_id', $detalle->id)->value('id');
                    app(AprobarRetornoPedido::class)->ejecutar($pedido, ['version' => 2, 'retorno_detalle_id' => $retorno, 'cantidad' => 1, 'motivo' => 'Reincorporación exclusivamente sintética.'], $administrador);
                }
            }
            if (! app(VerificadorInventario::class)->revisar()['correcto']) {
                throw new RuntimeException('La carga produjo una inconsistencia; conservar evidencia y detenerse sin reparar.');
            }
            file_put_contents($marca, json_encode(['autorizacion' => $m['autorizacion'], 'estado' => 'completa', 'clientes' => Cliente::count(), 'productos' => Producto::count(), 'pedidos' => Pedido::count()], JSON_THROW_ON_ERROR));
        } finally {
            Auth::logout();
        }
    }
}
