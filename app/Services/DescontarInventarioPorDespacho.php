<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use Illuminate\Validation\ValidationException;

/**
 * Descuenta las unidades terminadas al DESPACHAR, no al confirmar.
 * Debe ejecutarse DENTRO de la transaccion que bloquea el pedido
 * y actualiza el estado a "despachado".
 */
class DescontarInventarioPorDespacho
{
    public function ejecutar(Pedido $pedido, int $usuarioId): void
    {
        $detalles = $pedido->detalles()->get();

        if ($detalles->isEmpty()) {
            throw ValidationException::withMessages([
                'inventario' => 'No se puede despachar un pedido sin productos.',
            ]);
        }

        // Claves únicas ordenadas: se bloquean siempre en el mismo orden.
        $ids = $detalles->pluck('presentacion_producto_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()->sort()->values()->all();

        if (count($ids) === 0) {
            throw ValidationException::withMessages([
                'inventario' => 'Hay productos sin presentación de inventario asociada.',
            ]);
        }

        if ($detalles->contains(fn ($d) => $d->presentacion_producto_id === null)) {
            throw ValidationException::withMessages([
                'inventario' => 'El pedido contiene una presentación eliminada o sin SKU asociado.',
            ]);
        }

        // El catálogo respalda únicamente tamaños históricos que no se guardaron.
        // El precio del pedido nunca participa en la salida de inventario.
        $presentaciones = PresentacionProducto::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $cantidades = [];
        foreach ($detalles as $detalle) {
            $id = (int) $detalle->presentacion_producto_id;
            $presentacion = $presentaciones->get($id);

            if (! $presentacion) {
                throw ValidationException::withMessages([
                    'inventario' => 'La presentación del producto '.$detalle->codigo_sku.' no existe.',
                ]);
            }

            $unidades = (int) $detalle->cantidad;
            if ($unidades < 1) {
                throw ValidationException::withMessages([
                    'inventario' => 'La cantidad del producto '.$detalle->codigo_sku.' es inválida.',
                ]);
            }

            if ($detalle->modalidad === 'empaque') {
                $factor = (int) $detalle->unidadesPorEmpaqueEfectivas($presentacion);
                if ($factor < 1) {
                    throw ValidationException::withMessages([
                        'inventario' => 'Configura las unidades por empaque de '.$detalle->codigo_sku.' antes de despachar.',
                    ]);
                }
                $unidades *= $factor;
            } elseif ($detalle->modalidad !== 'unidad') {
                throw ValidationException::withMessages([
                    'inventario' => 'Modalidad no válida para '.$detalle->codigo_sku.'.',
                ]);
            }

            $cantidades[$id] = ($cantidades[$id] ?? 0) + $unidades;
        }

        $inventarios = Inventario::query()
            ->whereIn('presentacion_producto_id', $ids)
            ->orderBy('presentacion_producto_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('presentacion_producto_id');

        // Validación de TODOS los SKU antes de descontar uno solo.
        $faltantes = [];
        foreach ($cantidades as $id => $requerido) {
            $disponible = (int) ($inventarios->get($id)?->cantidad_actual ?? 0);
            if ($disponible < $requerido) {
                $sku = $presentaciones->get($id)->codigo_sku;
                $faltantes[] = 'Stock insuficiente para '.$sku
                        .': necesitas '.$requerido.' unidad(es) y hay '.$disponible
                        .'. Registra las existencias en Inventario antes del despacho.';
            }
        }

        if ($faltantes !== []) {
            throw ValidationException::withMessages(['inventario' => $faltantes]);
        }

        foreach ($cantidades as $id => $requerido) {
            $registro = $inventarios->get($id);
            $anterior = (int) $registro->cantidad_actual;
            $nuevo = $anterior - $requerido;

            $registro->update(['cantidad_actual' => $nuevo]);

            MovimientoInventario::create([
                'presentacion_producto_id' => $id,
                'insumo_id' => null,
                'pedido_id' => $pedido->id,
                'usuario_id' => $usuarioId,
                'tipo' => 'salida',
                'cantidad' => $requerido,
                'stock_anterior' => $anterior,
                'stock_nuevo' => $nuevo,
                'motivo' => 'Salida automática por despacho del pedido N.º '.$pedido->numero.'.',
            ]);
        }
    }
}
