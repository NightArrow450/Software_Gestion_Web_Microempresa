<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Models\VarianteProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListaGeneralPreparacionTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $permisos = ['pedidos.ver']): User
    {
        $rol = Role::create(['nombre' => 'Producción/Reparto', 'es_sistema' => true]);
        foreach ($permisos as $nombre) {
            $permiso = Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre]);
            $rol->permisos()->attach($permiso->id);
        }

        return User::create([
            'first_name' => 'Operador', 'last_name' => 'Prueba',
            'email' => 'operador@lista.test', 'password' => 'password123',
            'role_id' => $rol->id, 'status' => true,
        ]);
    }

    private function presentacion(string $sku, int $unidades = 4, string $nombre = 'Lejía'): PresentacionProducto
    {
        $categoria = Categoria::firstOrCreate(['nombre' => 'Limpieza'], ['estado' => true]);
        $producto = Producto::create([
            'codigo' => 'COD-'.$sku, 'categoria_id' => $categoria->id,
            'nombre' => $nombre, 'estado' => true,
        ]);

        return PresentacionProducto::create([
            'producto_id' => $producto->id,
            'codigo_sku' => $sku,
            'contenido' => 5, 'unidad_medida' => 'L',
            'tipo_envase' => 'Galón', 'tipo_empaque' => 'Paquete',
            'unidades_por_empaque' => $unidades,
            'venta_por_unidad' => true, 'venta_por_empaque' => true,
            'estado' => true,
        ]);
    }

    private function pedido(User $usuario, string $estado = 'confirmado', ?string $fecha = null): Pedido
    {
        $cliente = Cliente::firstOrCreate(['nombre' => 'Cliente prueba'], ['estado' => true]);

        return Pedido::create([
            'cliente_id' => $cliente->id, 'usuario_id' => $usuario->id,
            'fecha_pedido' => now('America/Lima')->toDateString(),
            'fecha_entrega' => $fecha ?? now('America/Lima')->addDay()->toDateString(),
            'condicion_pago' => 'contado', 'estado' => $estado,
            'total' => '0.00', 'cliente_nombre' => 'Cliente prueba',
        ]);
    }

    private function detalle(Pedido $pedido, ?PresentacionProducto $presentacion, string $modalidad, int $cantidad, ?string $sku = null, ?int $porEmpaque = null): void
    {
        $pedido->detalles()->create([
            'presentacion_producto_id' => $presentacion?->id,
            'codigo_sku' => $sku ?? $presentacion?->codigo_sku ?? 'HIST-SKU',
            'descripcion_producto' => $presentacion?->producto?->nombre ?? 'Producto histórico',
            'modalidad' => $modalidad,
            'unidades_por_empaque' => $porEmpaque,
            'cantidad' => $cantidad,
            'precio_unitario' => '10.00', 'importe' => number_format($cantidad * 10, 2, '.', ''),
        ]);
    }

    public function test_lista_muestra_tres_columnas_y_desglose_de_unidades_fisicas_por_empaque(): void
    {
        $usuario = $this->usuario();
        $presentacion = $this->presentacion('LEJ-5L', 4);
        $this->detalle($this->pedido($usuario), $presentacion, 'empaque', 3);

        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'))
            ->assertOk()
            ->assertSee('Presentación')
            ->assertSee('Ver detalle de empaques y unidades')
            ->assertSee('Total de unidades')
            ->assertSee('3 empaques')
            ->assertSee('3 empaques de 4')
            ->assertSee('>12</strong>', false);
    }

    public function test_tres_pedidos_suman_diez_empaques_y_cuarenta_unidades(): void
    {
        $usuario = $this->usuario();
        $presentacion = $this->presentacion('LEJ-5L', 4);
        foreach ([3, 5, 2] as $cantidad) {
            $this->detalle($this->pedido($usuario), $presentacion, 'empaque', $cantidad);
        }
        $contenido = $this->actingAs($usuario)
            ->get(route('cumplimiento.lista-preparacion'))->assertOk()->getContent();

        $this->assertStringContainsString('10 empaques', $contenido);
        $this->assertStringContainsString('>40</strong>', $contenido);
        $this->assertSame(1, substr_count($contenido, 'SKU: LEJ-5L'));
    }

    public function test_consolida_modalidades_y_excluye_estados_terminados(): void
    {
        $usuario = $this->usuario();
        $uno = $this->presentacion('LEJ-A', 4, 'Lejía');
        $dos = $this->presentacion('LEJ-B', 6, 'Lejía');
        $this->detalle($this->pedido($usuario, 'confirmado'), $uno, 'empaque', 3);
        $this->detalle($this->pedido($usuario, 'en_preparacion'), $uno, 'unidad', 5);
        $this->detalle($this->pedido($usuario, 'en_preparacion'), $dos, 'empaque', 2);
        foreach (['registrado', 'observado', 'cancelado', 'listo_despacho', 'despachado', 'entregado'] as $estado) {
            $excluido = $this->presentacion('EXCL-'.$estado, 4, 'No preparar '.$estado);
            $this->detalle($this->pedido($usuario, $estado), $excluido, 'unidad', 99);
        }

        $respuesta = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'));
        $respuesta->assertOk()->assertSee('3 empaques')->assertSee('5 unidades')
            ->assertSee('2 empaques')->assertSee('>17</strong>', false)->assertSee('>12</strong>', false);
        $html = $respuesta->getContent();
        $this->assertSame(1, substr_count($html, 'SKU: LEJ-A'));
        $this->assertSame(1, substr_count($html, 'SKU: LEJ-B'));
        foreach (['registrado', 'observado', 'cancelado', 'listo_despacho', 'despachado', 'entregado'] as $estado) {
            $respuesta->assertDontSee('SKU: EXCL-'.$estado);
        }
    }

    public function test_explicacion_visible_y_empaque_historico_sin_multiplicador(): void
    {
        $usuario = $this->usuario();
        $this->detalle($this->pedido($usuario), null, 'empaque', 2, 'SKU-HISTORICO');
        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'))
            ->assertOk()
            ->assertSee('SKU-HISTORICO')
            ->assertSee('2 empaques')
            ->assertSee('No disponible')
            ->assertSee('parcialmente preparadas')
            ->assertSee('catálogo actual');
    }

    public function test_filtra_fecha_de_entrega_y_por_defecto_desde_hoy_lima(): void
    {
        $usuario = $this->usuario();
        $presentacion = $this->presentacion('FECHA-1');
        $ayer = now('America/Lima')->subDay()->toDateString();
        $hoy = now('America/Lima')->toDateString();
        $manana = now('America/Lima')->addDay()->toDateString();
        $this->detalle($this->pedido($usuario, 'confirmado', $ayer), $presentacion, 'unidad', 90);
        $this->detalle($this->pedido($usuario, 'confirmado', $hoy), $presentacion, 'unidad', 2);
        $this->detalle($this->pedido($usuario, 'confirmado', $manana), $presentacion, 'unidad', 3);

        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'))
            ->assertOk()->assertSee('5 unidades')->assertDontSee('95 unidades')
            ->assertSee('name="fecha_desde"', false)->assertSee('Imprimir');

        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion', [
            'fecha_desde' => $ayer, 'fecha_hasta' => $hoy,
        ]))->assertOk()->assertSee('92 unidades');
    }

    public function test_rechaza_rango_de_fechas_invertido(): void
    {
        $usuario = $this->usuario();
        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion', [
            'fecha_desde' => '2026-10-20', 'fecha_hasta' => '2026-10-19',
        ]))->assertSessionHasErrors('fecha_hasta');
    }

    public function test_acceso_requiere_login_y_permiso_de_consulta(): void
    {
        $this->get(route('cumplimiento.lista-preparacion'))
            ->assertRedirect(route('login'));
        $sinPermiso = $this->usuario([]);
        $this->actingAs($sinPermiso)->get(route('cumplimiento.lista-preparacion'))
            ->assertForbidden();
    }

    public function test_lista_es_solo_lectura_y_el_detalle_individual_sigue_disponible(): void
    {
        $usuario = $this->usuario();
        $presentacion = $this->presentacion('SOLO-LECTURA', 4);
        $pedido = $this->pedido($usuario);
        $this->detalle($pedido, $presentacion, 'empaque', 2);

        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'))->assertOk();
        $this->actingAs($usuario)->get(route('cumplimiento.show', $pedido))
            ->assertOk()->assertSee('Ver lista general de preparación')
            ->assertSee('Ver pedido original');
        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => 'confirmado']);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_no_combina_skus_distintos_aunque_tengan_el_mismo_nombre(): void
    {
        $usuario = $this->usuario();
        $uno = $this->presentacion('DISTINTO-A', 4, 'Lejía');
        $dos = $this->presentacion('DISTINTO-B', 4, 'Lejía');
        $this->detalle($this->pedido($usuario), $uno, 'empaque', 1);
        $this->detalle($this->pedido($usuario), $dos, 'empaque', 1);
        $contenido = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'))
            ->assertOk()->getContent();
        $this->assertSame(1, substr_count($contenido, 'SKU: DISTINTO-A'));
        $this->assertSame(1, substr_count($contenido, 'SKU: DISTINTO-B'));
        $this->assertSame(2, substr_count($contenido, '1 empaque'));
    }

    public function test_unidades_vendidas_solas_no_se_multiplican_por_empaque(): void
    {
        $usuario = $this->usuario();
        $presentacion = $this->presentacion('VENTA-UNIDAD', 15);
        $this->detalle($this->pedido($usuario), $presentacion, 'unidad', 12);
        $respuesta = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'));
        $respuesta->assertOk()->assertSee('12 unidades')->assertSee('>12</strong>', false)
            ->assertDontSee('>180</strong>', false);
    }

    public function test_lista_vacia_no_cambia_inventario(): void
    {
        $usuario = $this->usuario();
        $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'))
            ->assertOk()->assertSee('No hay productos pendientes de preparación.');
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public static function cantidadesConsolidadas(): array
    {
        return ['solo empaques' => [0, 120], 'empaques y sueltas' => [10, 130]];
    }

    #[DataProvider('cantidadesConsolidadas')]
    public function test_consolida_empaques_historicos_y_unidades_sueltas(int $sueltas, int $total): void
    {
        $this->freezeTime();
        $usuario = $this->usuario();
        $presentacion = $this->presentacion('QUITA-1L', 12, 'Quita sarro');
        $presentacion->update(['contenido' => 1, 'tipo_envase' => 'Botella']);
        $this->detalle($this->pedido($usuario), $presentacion, 'empaque', 4, porEmpaque: 15);
        $this->detalle($this->pedido($usuario, 'en_preparacion'), $presentacion, 'empaque', 5, porEmpaque: 12);
        if ($sueltas > 0) {
            $this->detalle($this->pedido($usuario), $presentacion, 'unidad', $sueltas);
        }
        $presentacion->update(['unidades_por_empaque' => 24]);

        $respuesta = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'));
        $respuesta->assertOk()->assertSee('4 empaques de 15')->assertSee('5 empaques de 12')
            ->assertSee('Ver detalle de empaques y unidades');
        $filas = $respuesta->viewData('filas');
        $this->assertCount(1, $filas);
        $this->assertSame('1 L', $filas[0]['presentacion']);
        $this->assertSame($total, $filas[0]['total_unidades']);
        $this->assertSame(60, $filas[0]['desglose']['empaque|15']['total_unidades']);
        $this->assertSame(60, $filas[0]['desglose']['empaque|12']['total_unidades']);
        $this->assertSame(1, substr_count($respuesta->getContent(), 'SKU: QUITA-1L'));
    }

    public function test_presentaciones_comerciales_distintas_con_misma_fisica_se_consolidan(): void
    {
        $usuario = $this->usuario();
        $uno = $this->presentacion('MISMA-FISICA-12', 12);
        $dos = $uno->replicate();
        $dos->codigo_sku = 'MISMA-FISICA-15';
        $dos->unidades_por_empaque = 15;
        $dos->tipo_empaque = 'Caja';
        $dos->save();
        $this->detalle($this->pedido($usuario), $uno, 'empaque', 5, porEmpaque: 12);
        $this->detalle($this->pedido($usuario), $dos, 'empaque', 4, porEmpaque: 15);

        $respuesta = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'));
        $respuesta->assertOk()->assertSee('MISMA-FISICA-12')->assertSee('MISMA-FISICA-15');
        $this->assertCount(1, $respuesta->viewData('filas'));
        $this->assertSame(120, $respuesta->viewData('filas')[0]['total_unidades']);
    }

    public function test_un_litro_y_cuatro_litros_del_mismo_producto_no_se_mezclan(): void
    {
        $usuario = $this->usuario();
        $uno = $this->presentacion('QUITA-1L', 12, 'Quita sarro');
        $uno->update(['contenido' => 1]);
        $cuatro = $uno->replicate();
        $cuatro->codigo_sku = 'QUITA-4L';
        $cuatro->contenido = 4;
        $cuatro->save();
        $this->detalle($this->pedido($usuario), $uno, 'empaque', 4, porEmpaque: 15);
        $this->detalle($this->pedido($usuario), $cuatro, 'unidad', 5);

        $respuesta = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'));
        $respuesta->assertOk()->assertSee('1 L')->assertSee('4 L');
        $this->assertSame(['1 L' => 60, '4 L' => 5], $respuesta->viewData('filas')->pluck('total_unidades', 'presentacion')->all());
    }

    public function test_variantes_fisicas_y_tipos_de_envase_no_se_mezclan(): void
    {
        $usuario = $this->usuario();
        $base = $this->presentacion('BASE', 12, 'Limpiador');
        $base->update(['contenido' => 1, 'tipo_envase' => 'Botella']);
        $pedido = $this->pedido($usuario);
        foreach (['Limón', 'Lavanda'] as $valor) {
            $variante = VarianteProducto::create(['producto_id' => $base->producto_id, 'tipo' => 'Aroma', 'valor' => $valor, 'estado' => true]);
            $presentacion = $base->replicate();
            $presentacion->codigo_sku = 'AROMA-'.$variante->id;
            $presentacion->variante_producto_id = $variante->id;
            $presentacion->save();
            $this->detalle($pedido, $presentacion, 'unidad', 3);
        }
        $bolsa = $base->replicate();
        $bolsa->codigo_sku = 'BASE-BOLSA';
        $bolsa->tipo_envase = 'Bolsa';
        $bolsa->save();
        $this->detalle($pedido, $base, 'unidad', 5);
        $this->detalle($pedido, $bolsa, 'unidad', 7);

        $respuesta = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'));
        $respuesta->assertOk()->assertSee('Variante: Limón')->assertSee('Variante: Lavanda')->assertSee('Botella')->assertSee('Bolsa');
        $this->assertCount(4, $respuesta->viewData('filas'));
        $this->assertSame([3, 3, 5, 7], $respuesta->viewData('filas')->pluck('total_unidades')->all());
    }

    public function test_datos_historicos_desconocidos_no_inventan_un_total_consolidado(): void
    {
        $usuario = $this->usuario();
        $this->detalle($this->pedido($usuario), null, 'empaque', 2, 'HIST-1L');
        $this->detalle($this->pedido($usuario), null, 'unidad', 10, 'HIST-1L');
        $this->detalle($this->pedido($usuario), null, 'unidad', 5, 'HIST-4L');

        $respuesta = $this->actingAs($usuario)->get(route('cumplimiento.lista-preparacion'));
        $respuesta->assertOk()->assertSee('No disponible')->assertSee('2 empaques de tamaño no disponible')->assertSee('10 unidades');
        $filas = $respuesta->viewData('filas');
        $this->assertCount(2, $filas);
        $this->assertNull($filas[0]['total_unidades']);
        $this->assertSame(10, $filas[0]['desglose']['unidad|0']['total_unidades']);
        $this->assertSame(5, $filas[1]['total_unidades']);
    }
}
