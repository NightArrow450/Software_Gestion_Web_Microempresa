<?php

namespace Tests;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ConfiguracionEnvasado;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MaterialEnvasado;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

trait EnvasadoEscenario
{
    protected function escenarioEnvasado(int $stockMaterial = 100, int $stockProducto = 0): array
    {
        config(['envasado.control_materiales_exacto' => true]);
        $sufijo = (string) Str::ulid();
        $rol = Role::create(['nombre' => 'Prueba '.$sufijo, 'es_sistema' => false]);
        foreach (['envasado.ver', 'envasado.registrar', 'envasado.corregir', 'envasado.reportar', 'materiales.envasado.configurar', 'pedidos.ver', 'cumplimiento.despachar', 'inventario.ver', 'inventario.entrada'] as $nombre) {
            $rol->permisos()->attach(Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }
        $usuario = User::create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => $sufijo.'@envasado.test', 'password' => 'ClaveSegura123', 'role_id' => $rol->id, 'status' => true]);
        $categoria = Categoria::firstOrCreate(['nombre' => 'Limpieza'], ['estado' => true]);
        $producto = Producto::create(['codigo' => 'P'.substr($sufijo, -16), 'nombre' => 'Producto de prueba', 'categoria_id' => $categoria->id, 'estado' => true]);
        $presentacion = PresentacionProducto::create(['producto_id' => $producto->id, 'codigo_sku' => 'S'.$sufijo, 'contenido' => 1, 'unidad_medida' => 'L', 'tipo_envase' => 'Botella', 'unidades_por_empaque' => 12, 'venta_por_unidad' => true, 'venta_por_empaque' => true, 'precio_unitario' => 2, 'estado' => true]);
        Inventario::create(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => $stockProducto]);
        $insumo = Insumo::create(['codigo' => 'I'.$sufijo, 'nombre' => 'Envase de prueba', 'categoria' => 'Envases', 'unidad_medida' => 'unidad', 'cantidad_actual' => $stockMaterial, 'estado' => true]);
        $configuracion = ConfiguracionEnvasado::factory()->create(['presentacion_producto_id' => $presentacion->id]);
        MaterialEnvasado::factory()->create(['configuracion_envasado_id' => $configuracion->id, 'insumo_id' => $insumo->id]);

        return compact('usuario', 'presentacion', 'insumo', 'configuracion');
    }

    protected function pedidoEnvasado(array $escenario, string $estado = 'confirmado'): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente de prueba', 'estado' => true]);
        $pedido = Pedido::create(['cliente_id' => $cliente->id, 'usuario_id' => $escenario['usuario']->id, 'cliente_nombre' => $cliente->nombre, 'estado' => $estado, 'fecha_pedido' => today(), 'fecha_entrega' => today(), 'condicion_pago' => 'contado', 'total' => 10]);
        $pedido->detalles()->create(['presentacion_producto_id' => $escenario['presentacion']->id, 'codigo_sku' => $escenario['presentacion']->codigo_sku, 'descripcion_producto' => 'Producto de prueba', 'modalidad' => 'unidad', 'cantidad' => 5, 'precio_unitario' => 2, 'importe' => 10]);

        return $pedido;
    }
}
