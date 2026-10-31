<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Services\GestionarPedidoUnidades;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class RetiroControladoSurtidosTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/retiro_controlado/2026_10_30_003639_retirar_estructuras_surtidos_vacias.php');
    }

    private function migracionDefinitiva(): object
    {
        return require database_path('migrations/retiro_controlado/2026_10_31_174658_retirar_definitivamente_surtidos_obsoletos.php');
    }

    public function test_retiro_definitivo_exige_su_autorizacion_propia(): void
    {
        putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS');
        try {
            $this->migracionDefinitiva()->up();
            $this->fail('La autorización antigua no permite eliminar datos obsoletos.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Retiro bloqueado', $error->getMessage());
        } finally {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
        }
        $this->assertTrue(Schema::hasTable('formatos_surtidos'));
    }

    public function test_retiro_definitivo_elimina_catalogo_obsoleto_sin_tocar_datos_vigentes(): void
    {
        $e = $this->escenarioEnvasado();
        DB::table('formatos_surtidos')->insert(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'Formato ficticio obsoleto', 'tipo_envase' => 'Botella',
            'contenido' => 1, 'unidad_medida' => 'L', 'unidades_por_empaque' => 12, 'precio_empaque' => 24, 'estado' => true]);
        $originales = [];
        foreach (['users', 'roles', 'permisos', 'rol_permiso', 'productos', 'presentaciones_producto', 'inventarios', 'movimientos_inventario'] as $tabla) {
            $originales[$tabla] = DB::table($tabla)->get()->toArray();
        }
        putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_ESTRUCTURAS_SURTIDOS_OBSOLETAS');
        try {
            $this->migracionDefinitiva()->up();
            $this->migracionDefinitiva()->up();
        } finally {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
        }
        $this->assertCount(46, Schema::getTableListing(null, false));
        $this->assertFalse(Schema::hasTable('formatos_surtidos'));
        $this->assertFalse(Schema::hasColumn('pedido_detalles', 'formato_surtido_id'));
        $this->assertTrue(Schema::hasColumn('pedido_detalles', 'tipo_linea'));
        foreach ($originales as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
    }

    public static function bloqueosDefinitivos(): array
    {
        return ['vista' => ['vista'], 'trigger' => ['trigger'], 'indice compuesto' => ['indice'], 'dependencia de cuenta' => ['cuenta'], 'linea comercial' => ['linea'], 'retiro parcial' => ['parcial']];
    }

    #[DataProvider('bloqueosDefinitivos')]
    public function test_retiro_definitivo_aborta_ante_dependencias_antes_de_ddl(string $tipo): void
    {
        if ($tipo === 'vista') {
            DB::unprepared('CREATE VIEW dependencia_retiro AS SELECT id FROM formatos_surtidos');
        } elseif ($tipo === 'trigger') {
            DB::unprepared('CREATE TRIGGER dependencia_retiro BEFORE UPDATE ON clientes BEGIN SELECT COUNT(*) FROM formatos_surtidos; END');
        } elseif ($tipo === 'indice') {
            Schema::table('pedido_detalles', fn (Blueprint $tabla) => $tabla->index(['formato_surtido_id', 'pedido_id'], 'dependencia_retiro'));
        } elseif ($tipo === 'cuenta') {
            Schema::table('users', fn (Blueprint $tabla) => $tabla->foreignId('formato_anterior_id')->nullable()->constrained('formatos_surtidos'));
        } elseif ($tipo === 'linea') {
            $e = $this->escenarioEnvasado();
            $pedido = $this->pedidoEnvasado($e);
            $pedido->detalles()->update(['tipo_linea' => 'surtido']);
        } else {
            Schema::drop('pedido_despacho_componentes');
        }
        putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_ESTRUCTURAS_SURTIDOS_OBSOLETAS');
        try {
            $this->migracionDefinitiva()->up();
            $this->fail('No debe iniciar DDL con una dependencia no resuelta.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('No se ha iniciado DDL', $error->getMessage());
        } finally {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
        }
        $this->assertTrue(Schema::hasTable('formatos_surtidos'));
        $this->assertTrue(Schema::hasColumn('pedido_detalles', 'formato_surtido_id'));
        $this->assertDatabaseCount('migrations', 48);
    }

    public function test_el_retiro_no_se_ejecuta_con_migraciones_ordinarias_y_exige_autorizacion(): void
    {
        $this->assertDatabaseCount('migrations', 48);
        $this->assertTrue(Schema::hasTable('formatos_surtidos'));
        try {
            $this->migracion()->up();
            $this->fail('La migración manual no debe actuar sin autorización específica.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Retiro bloqueado', $error->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('pedido_detalles', 'formato_surtido_id'));
    }

    public function test_retiro_solo_de_tablas_vacias_conserva_cuentas_y_permite_registrar_unidades(): void
    {
        $e = $this->escenarioEnvasado();
        $e['usuario']->role->permisos()->attach(Permiso::firstOrCreate(['nombre' => 'pedidos.crear'])->id);
        $cuentas = [];
        foreach (['users', 'roles', 'permisos', 'rol_permiso'] as $tabla) {
            $cuentas[$tabla] = DB::table($tabla)->get()->toArray();
        }
        putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS');
        try {
            $this->migracion()->up();
        } finally {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
        }
        foreach (['formatos_surtidos', 'formato_surtido_skus', 'pedido_paquetes_surtidos', 'paquete_surtido_composiciones', 'paquete_surtido_componentes', 'pedido_despacho_componentes'] as $tabla) {
            $this->assertFalse(Schema::hasTable($tabla));
        }
        $cliente = Cliente::create(['nombre' => 'Cliente de unidades', 'estado' => true]);
        $pedido = app(GestionarPedidoUnidades::class)->guardar(['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(),
            'condicion_pago' => 'contado', 'detalles' => [['presentacion_producto_id' => $e['presentacion']->id, 'cantidad' => 7]]], $e['usuario']);
        $this->assertSame('14.00', $pedido->total);
        $this->assertSame(7, (int) $pedido->detalles()->sole()->cantidad);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        foreach ($cuentas as $tabla => $filas) {
            $this->assertEquals($filas, DB::table($tabla)->get()->toArray(), $tabla);
        }
    }

    public function test_si_hay_datos_obsoletos_no_retira_ninguna_tabla_ni_modifica_filas(): void
    {
        $e = $this->escenarioEnvasado();
        DB::table('formatos_surtidos')->insert(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'Dato conservado', 'tipo_envase' => 'Botella',
            'contenido' => 1, 'unidad_medida' => 'L', 'unidades_por_empaque' => 12, 'precio_empaque' => 24, 'estado' => true]);
        putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS');
        try {
            $this->migracion()->up();
            $this->fail('La precomprobación debe fallar antes del primer cambio DDL.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('debe existir y estar vacía', $error->getMessage());
        } finally {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
        }
        $this->assertDatabaseCount('formatos_surtidos', 1);
        $this->assertTrue(Schema::hasTable('pedido_despacho_componentes'));
        $this->assertTrue(Schema::hasColumn('pedido_detalles', 'formato_surtido_id'));
    }

    public function test_dependencia_no_prevista_detiene_el_retiro_antes_de_modificar_el_esquema(): void
    {
        Schema::create('dependencia_no_prevista', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('formato_id')->constrained('formatos_surtidos');
        });
        putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS');
        try {
            $this->migracion()->up();
            $this->fail('Debe detectar dependencias no contempladas antes de cualquier cambio.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Dependencia no prevista', $error->getMessage());
        } finally {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
        }
        $this->assertTrue(Schema::hasTable('formatos_surtidos'));
        $this->assertTrue(Schema::hasColumn('pedido_detalles', 'formato_surtido_id'));
        $this->assertDatabaseCount('migrations', 48);
    }

    public static function dependenciasSql(): array
    {
        return ['trigger externo' => ['trigger'], 'vista externa' => ['view']];
    }

    #[DataProvider('dependenciasSql')]
    public function test_dependencias_sql_externas_bloquean_el_retiro_antes_de_ddl(string $tipo): void
    {
        if ($tipo === 'trigger') {
            DB::unprepared('CREATE TRIGGER dependencia_sql_prueba BEFORE UPDATE ON clientes BEGIN SELECT COUNT(*) FROM formatos_surtidos; END');
        } else {
            DB::unprepared('CREATE VIEW dependencia_sql_prueba AS SELECT id FROM formatos_surtidos');
        }
        putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS');
        try {
            $this->migracion()->up();
            $this->fail('Debe detectar dependencias SQL antes de retirar columnas, triggers o tablas.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('No se ha iniciado DDL', $error->getMessage());
        } finally {
            putenv('DHACER_RETIRO_SURTIDOS_AUTORIZADO');
        }
        $this->assertTrue(Schema::hasTable('formatos_surtidos'));
        $this->assertTrue(Schema::hasColumn('pedido_detalles', 'formato_surtido_id'));
        $this->assertSame(1, DB::table('sqlite_master')->where('name', 'dependencia_sql_prueba')->count());
        $this->assertDatabaseCount('migrations', 48);
    }
}
