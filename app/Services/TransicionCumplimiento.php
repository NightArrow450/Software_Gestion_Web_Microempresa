<?php

namespace App\Services;

use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transiciones del cumplimiento: siempre en transaccion, bloqueo de fila e historial.
 * No modifica las existencias del inventario (Sprint 3).
 */
class TransicionCumplimiento
{
    public function registrar(
        Pedido $pedido,
        string $estadoRequerido,
        string $estadoNuevo,
        int $usuarioId,
        array $datosAdicionales,
        ?string $motivo
    ): void {
        DB::transaction(function () use (
            $pedido, $estadoRequerido, $estadoNuevo,
            $usuarioId, $datosAdicionales, $motivo
        ) {
            $actual = Pedido::query()->whereKey($pedido->id)
                ->lockForUpdate()->firstOrFail();

            if ($actual->estado !== $estadoRequerido) {
                throw ValidationException::withMessages([
                    'estado' => 'Operación no permitida: el pedido debe estar en estado '
                        .str_replace('_', ' ', $estadoRequerido).'.',
                ]);
            }

            $anterior = $actual->estado;
            DB::table('pedidos')->where('id', $actual->id)->update(array_merge(
                [
                    'estado' => $estadoNuevo,
                    'updated_at' => now(),
                ],
                $datosAdicionales
            ));

            $actual->historialEstados()->create([
                'usuario_id' => $usuarioId,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $estadoNuevo,
                'motivo' => $motivo,
            ]);
        });
    }
}
