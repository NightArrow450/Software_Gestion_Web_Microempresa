<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PrepararSaneamientoComercial extends Command
{
    protected $signature = 'comercio:preparar-saneamiento {--base= : Base exacta esperada} {--uuid= : UUID MySQL esperado}';

    protected $description = 'Prepara un manifiesto de solo lectura; nunca elimina registros ni estructuras';

    private const PROTEGIDAS = ['users', 'roles', 'permisos', 'rol_permiso', 'password_reset_tokens', 'sessions', 'migrations', 'auditoria', 'solicitudes_idempotentes', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const COMERCIALES = ['categorias', 'clientes', 'configuraciones_envasado', 'entrega_pedido_detalles', 'entregas_pedido', 'envasado_consumos', 'envasado_correccion_materiales', 'envasado_correcciones', 'envasado_reportes', 'envasados', 'formato_surtido_skus', 'formatos_surtidos', 'incidencia_entrega_eventos', 'incidencias_entrega', 'insumos', 'inventarios', 'materiales_envasado', 'movimientos_inventario', 'paquete_surtido_componentes', 'paquete_surtido_composiciones', 'pedido_agrupacion_detalles', 'pedido_agrupaciones', 'pedido_despacho_agrupaciones', 'pedido_despacho_componentes', 'pedido_despacho_lineas', 'pedido_despachos', 'pedido_detalles', 'pedido_estados_historial', 'pedido_paquetes_surtidos', 'pedido_preparaciones', 'pedido_referencias_fisicas', 'pedido_versiones', 'pedidos', 'presentaciones_producto', 'productos', 'retorno_pedido_detalles', 'retornos_pedido', 'variantes_producto'];

    public function handle(): int
    {
        $base = DB::getDatabaseName();
        $pruebas = app()->environment('testing') && ($base === ':memory:' || preg_match('/\Adhacer_eco_limp_test_[a-z0-9_]+\z/', $base));
        if ($this->option('base') !== $base || (! $pruebas && $base !== 'software_gestion_web_microempresa')) {
            throw new RuntimeException('Identidad de base no autorizada. No se ha modificado ningún dato.');
        }
        $identidad = ['motor' => DB::getDriverName(), 'base' => $base];
        if (DB::getDriverName() === 'mysql') {
            $servidor = DB::selectOne('SELECT @@server_uuid uuid, @@hostname servidor, @@port puerto, @@datadir directorio, VERSION() version');
            if (! $this->option('uuid') || $this->option('uuid') !== $servidor->uuid) {
                throw new RuntimeException('UUID MySQL distinto del autorizado. No se ha modificado ningún dato.');
            }
            $identidad += (array) $servidor;
        } elseif (! $pruebas || $base !== ':memory:') {
            throw new RuntimeException('Solo MySQL verificado o SQLite en memoria de pruebas.');
        }
        $tablas = Schema::getTableListing(DB::getDriverName() === 'mysql' ? $base : null, false);
        $desconocidas = array_diff($tablas, self::PROTEGIDAS, self::COMERCIALES);
        if ($desconocidas !== []) {
            throw new RuntimeException('Tablas sin clasificar: '.implode(', ', $desconocidas).'. Revisar sin eliminar nada.');
        }
        $comerciales = array_values(array_intersect($tablas, self::COMERCIALES));
        $dependencias = [];
        $bloqueos = [];
        foreach ($tablas as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $clave) {
                if (in_array($clave['foreign_table'], $comerciales, true)) {
                    if (in_array($tabla, self::PROTEGIDAS, true)) {
                        $bloqueos[] = $tabla.' → '.$clave['foreign_table'];
                    } else {
                        $dependencias[$tabla][] = $clave['foreign_table'];
                    }
                }
            }
        }
        $orden = [];
        $pendientes = $comerciales;
        while ($pendientes !== []) {
            $elegibles = array_values(array_filter($pendientes, fn (string $tabla): bool => ! collect($dependencias)->contains(fn (array $padres, string $hija): bool => in_array($hija, $pendientes, true) && in_array($tabla, $padres, true))));
            if ($elegibles === []) {
                throw new RuntimeException('Ciclo de dependencias comerciales. No se prepara eliminación automática.');
            }
            sort($elegibles);
            array_push($orden, ...$elegibles);
            $pendientes = array_values(array_diff($pendientes, $elegibles));
        }
        $huellas = [];
        foreach ($tablas as $tabla) {
            $filas = [];
            foreach (DB::table($tabla)->cursor() as $fila) {
                $datos = (array) $fila;
                ksort($datos);
                $filas[] = hash('sha256', serialize($datos));
            }
            sort($filas);
            $huellas[$tabla] = ['registros' => count($filas), 'sha256_contenido' => hash('sha256', implode('', $filas)), 'tratamiento' => in_array($tabla, self::PROTEGIDAS, true) ? 'conservar íntegramente' : 'candidato comercial; requiere autorización posterior'];
        }
        $this->line(json_encode(['modo' => 'SOLO_LECTURA_NO_EJECUTA_LIMPIEZA', 'identidad' => $identidad, 'tablas' => $huellas, 'orden_candidato_hijas_antes_de_padres' => $orden, 'bloqueos' => $bloqueos,
            'advertencias' => ['Los triggers de inmutabilidad siguen activos: este manifiesto no autoriza ni ejecuta DELETE ni DROP.', 'Auditoría e idempotencia se conservan completas por sus referencias de cuentas; requieren clasificación separada antes del saneamiento final.', 'Requiere ausencia verificada de escritores y repetición de huellas inmediatamente antes y después de cualquier limpieza autorizada.']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $bloqueos === [] ? self::SUCCESS : self::FAILURE;
    }
}
