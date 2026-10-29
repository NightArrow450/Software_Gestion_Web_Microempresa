<?php

namespace Tests;

use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\VarianteProducto;
use App\Services\CalculadoraPedido;
use App\Services\ConfigurarFormatoSurtido;
use App\Services\GestionarSurtidos;

trait SurtidosEscenario
{
    use EnvasadoEscenario;

    protected function escenarioSurtido(int $stock = 100): array
    {
        $e = $this->escenarioEnvasado(100, $stock);
        foreach (['surtidos.configurar', 'pedidos.surtidos.componer', 'pedidos.crear', 'pedidos.editar', 'cumplimiento.preparar', 'cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar', 'cumplimiento.incidencias.corregir', 'seguimiento.ver'] as $nombre) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }
        $e['skus'] = [];
        foreach (['Lavanda', 'Limón', 'Floral'] as $i => $aroma) {
            $variante = VarianteProducto::create(['producto_id' => $e['presentacion']->producto_id, 'tipo' => 'Aroma', 'valor' => $aroma, 'estado' => true]);
            if ($i === 0) {
                $sku = $e['presentacion'];
                $sku->update(['variante_producto_id' => $variante->id]);
            } else {
                $sku = PresentacionProducto::create(array_replace($e['presentacion']->getAttributes(), ['id' => null, 'codigo_sku' => $e['presentacion']->codigo_sku.'-'.$i, 'variante_producto_id' => $variante->id]));
                Inventario::create(['presentacion_producto_id' => $sku->id, 'cantidad_actual' => $stock]);
            }
            $e['skus'][] = $sku;
        }
        $this->actingAs($e['usuario']);
        $e['formato'] = app(ConfigurarFormatoSurtido::class)->guardar(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'Surtido de prueba 1 L', 'unidades_por_empaque' => 12, 'precio_empaque' => '24.00', 'estado' => true, 'skus' => array_column($e['skus'], 'id')], $e['usuario']);

        return $e;
    }

    protected function paquetesSurtidos(array $e): array
    {
        return array_map(fn (array $cantidades): array => ['estado' => 'definida', 'componentes' => array_map(fn (int $i): array => ['presentacion_producto_id' => $e['skus'][$i]->id, 'cantidad' => $cantidades[$i]], [0, 1, 2])], [[5, 4, 3], [2, 7, 3]]);
    }

    /** Fotografía ficticia anterior al rediseño; no habilita altas comerciales antiguas. */
    protected function crearPedidoHistoricoSurtido(array $e, ?array $paquetes = null, array $otros = []): Pedido
    {
        $pedido = $this->pedidoEnvasado($e, 'registrado');
        $pedido->detalles()->delete();
        $fila = ['tipo_linea' => 'surtido', 'formato_surtido_id' => $e['formato'], 'modalidad' => 'empaque', 'cantidad' => 2, 'unidades_por_empaque' => 12];
        $datos = app(GestionarSurtidos::class)->prepararLinea($fila, $e['usuario']);
        $detalle = $pedido->detalles()->create($datos);
        app(GestionarSurtidos::class)->guardar($detalle, $paquetes ?? $this->paquetesSurtidos($e), $e['usuario'], 'Fotografía histórica ficticia para verificar compatibilidad.');
        $total = (int) round((float) $datos['importe'] * 100);
        foreach ($otros as $otra) {
            $sku = PresentacionProducto::findOrFail($otra['presentacion_producto_id']);
            $factor = $otra['modalidad'] === 'empaque' ? (int) ($otra['unidades_por_empaque'] ?? $sku->unidades_por_empaque) : 1;
            $precio = $otra['modalidad'] === 'empaque' ? app(CalculadoraPedido::class)->calcularImporte($factor, $sku->precio_unitario) : $sku->precio_unitario;
            $importe = app(CalculadoraPedido::class)->calcularImporte($otra['cantidad'], $precio);
            $pedido->detalles()->create($otra + ['codigo_sku' => $sku->codigo_sku, 'descripcion_producto' => app(GestionarSurtidos::class)->nombre($sku), 'precio_unitario' => $precio, 'importe' => $importe]);
            $total += (int) round((float) $importe * 100);
        }
        $pedido->update(['total' => number_format($total / 100, 2, '.', '')]);
        $pedido->historialEstados()->create(['usuario_id' => $e['usuario']->id, 'estado_nuevo' => 'registrado', 'motivo' => 'Registro histórico ficticio.']);

        return $pedido->fresh();
    }
}
