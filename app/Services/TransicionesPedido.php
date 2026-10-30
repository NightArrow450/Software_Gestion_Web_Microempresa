<?php

namespace App\Services;

use App\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Las transiciones de cumplimiento son atómicas y auditadas.
 * Al despachar descuenta el stock real en la MISMA transacción.
 */
class TransicionesPedido
{
    public function __construct(
        private readonly DescontarInventarioPorDespacho $inventarioDespacho
    ) {}

    public function registrar(
        Pedido $pedido,
        ?string $estadoRequerido,
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

            if (($estadoRequerido !== null && $actual->estado !== $estadoRequerido) || ! (EstadoPedido::tryFrom($actual->estado)?->permite(EstadoPedido::from($estadoNuevo)) ?? false)) {
                throw ValidationException::withMessages([
                    'estado' => 'Operación no permitida: el pedido debe estar en estado '
                        .str_replace('_', ' ', $estadoRequerido ?? $actual->estado).'.',
                ]);
            }

            if ($estadoNuevo === 'confirmado' && (! $actual->detalles()->exists() || ! $actual->cliente()->exists())) {
                throw ValidationException::withMessages(['estado' => 'El pedido requiere cliente y productos.']);
            }
            if (in_array($estadoNuevo, ['confirmado', 'observado'], true)) {
                $datosAdicionales['procesado_por_id'] = $usuarioId;
                $datosAdicionales['procesado_en'] = now();
            }
            // Solo DESPACHO genera salida automática. Si falta cualquier
            // producto, se abortan el stock, el historial y el cambio de estado.
            if ($estadoNuevo === 'listo_despacho') {
                if ($actual->modelo_comercial === 'unidades') {
                    app(AtenderPedidoUnidades::class)->validarCierre($actual);
                }
                app(LecturaPedido::class)->validarCierre($actual);
            }
            if ($estadoNuevo === 'despachado') {
                if ($actual->modelo_comercial === 'unidades') {
                    throw ValidationException::withMessages(['estado' => 'Registra el despacho por cantidades realmente preparadas; no se descuenta el pedido completo automáticamente.']);
                }
                $this->inventarioDespacho->ejecutar($actual, $usuarioId);
            }

            $anterior = $actual->estado;
            DB::table('pedidos')->where('id', $actual->id)->update(array_merge(
                [
                    'estado' => $estadoNuevo,
                    'updated_at' => now(), 'version' => DB::raw('version + 1'),
                ],
                $datosAdicionales
            ));

            $actual->historialEstados()->create([
                'usuario_id' => $usuarioId,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $estadoNuevo,
                'motivo' => $motivo,
            ]);
            if ($estadoNuevo === 'cancelado') {
                RegistroAuditoria::registrar('pedido_cancelado', 'pedido', $actual->id, ['motivo' => $motivo]);
            }

        }, 3);
    }
}
