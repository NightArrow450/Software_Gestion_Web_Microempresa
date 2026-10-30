<?php

namespace Tests;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\VarianteProducto;
use App\Services\AtenderPedidoUnidades;
use App\Services\GestionarPedidoUnidades;
use Illuminate\Support\Str;

trait VentaUnidadesEscenario
{
    use EnvasadoEscenario;

    protected function escenarioUnidades(int $stock = 100): array
    {
        $e = $this->escenarioEnvasado(100, $stock);
        foreach (['pedidos.crear', 'pedidos.editar', 'cumplimiento.preparar', 'cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar', 'cumplimiento.incidencias.corregir', 'seguimiento.ver'] as $nombre) {
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
        $e['usuario']->unsetRelation('role');
        $this->actingAs($e['usuario']);

        return $e;
    }

    protected function agrupacionesUnidades(array $e): array
    {
        return array_map(fn (array $cantidades): array => ['capacidad' => 12, 'componentes' => array_map(fn (int $i): array => ['sku_id' => $e['skus'][$i]->id, 'cantidad' => $cantidades[$i]], [0, 1, 2])], [[5, 4, 3], [2, 7, 3]]);
    }

    protected function entradaUnidades(array $e, array $cantidades = [7, 11, 6], array $grupos = []): array
    {
        $cliente = Cliente::create(['nombre' => 'Cliente ficticio por unidades', 'estado' => true]);

        return ['cliente_id' => $cliente->id, 'fecha_pedido' => today()->toDateString(), 'fecha_entrega' => today()->toDateString(), 'condicion_pago' => 'contado',
            'detalles' => array_map(fn ($sku, int $cantidad): array => ['presentacion_producto_id' => $sku->id, 'cantidad' => $cantidad], $e['skus'], $cantidades), 'agrupaciones' => $grupos];
    }

    protected function registrarUnidades(array $e, array $cantidades = [7, 11, 6], array $grupos = []): Pedido
    {
        return app(GestionarPedidoUnidades::class)->guardar($this->entradaUnidades($e, $cantidades, $grupos), $e['usuario']);
    }

    protected function despacharUnidades(Pedido $pedido, array $e): int
    {
        $pedido->update(['estado' => 'confirmado']);
        $pedido->refresh();
        $servicio = app(AtenderPedidoUnidades::class);
        foreach ($pedido->detalles()->get() as $detalle) {
            $servicio->preparar($pedido, ['version_pedido' => $pedido->version, 'pedido_detalle_id' => $detalle->id, 'cantidad' => $detalle->cantidad,
                'motivo' => 'Se comprobaron físicamente las unidades para este ensayo aislado.'], $e['usuario']);
            $pedido->refresh();
        }

        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id] ?? [];

        return $servicio->despachar($pedido, ['paquetes' => array_column($foto['agrupaciones'] ?? [], 'identidad'), 'version_pedido' => $pedido->version, 'operacion' => (string) Str::ulid(),
            'motivo' => 'Despacho físico en el entorno independiente de pruebas.',
            'detalles' => $pedido->detalles()->get()->map(fn ($d): array => ['id' => $d->id, 'cantidad' => $d->cantidad])->all()], $e['usuario']);
    }
}
