<?php

namespace App\Services;

use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Las transiciones de cumplimiento son atómicas y auditadas.
 * Al despachar descuenta el stock real en la MISMA transacción.
 */
class TransicionCumplimiento
{
    public function __construct(
        private readonly DescontarInventarioPorDespacho $inventarioDespacho
    ) {
    }

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

            // Solo DESPACHO genera salida automática. Si falta cualquier
            // producto, se abortan el stock, el historial y el cambio de estado.
            if ($estadoNuevo === 'despachado') {
                $this->inventarioDespacho->ejecutar($actual, $usuarioId);
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
        }, 3);
    }
}
