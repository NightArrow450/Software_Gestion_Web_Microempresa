<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RetirarFaltantesPedido
{
    public function resumen(Pedido $pedido): array
    {
        if ($pedido->modelo_comercial !== 'unidades' || in_array($pedido->estado, ['entregado', 'rechazado', 'cancelado'], true)) {
            return [];
        }
        $cerrado = DB::table('incidencias_entrega')->where('pedido_id', $pedido->id)->whereNotNull('resuelto_en')->exists();
        if ($cerrado) {
            return [];
        }
        $salidas = app(AtenderPedidoUnidades::class)->despachadas($pedido->id);

        return $pedido->detalles()->get()->map(fn ($d): array => ['id' => $d->id, 'sku' => $d->codigo_sku, 'nombre' => $d->descripcion_producto,
            'vigentes' => (int) $d->cantidad, 'despachadas' => (int) $salidas->get($d->id, 0), 'pendientes' => max(0, (int) $d->cantidad - (int) $salidas->get($d->id, 0)),
            'espera' => (int) $d->cantidad_espera, 'precio_centimos' => (int) round((float) $d->precio_unitario * 100)])
            ->filter(fn (array $d): bool => $d['pendientes'] > 0)->values()->all();
    }

    public function ejecutar(Pedido $referencia, array $entrada, User $usuario): Pedido
    {
        abort_unless($usuario->status && $usuario->role?->tienePermiso('pedidos.editar'), 403);
        $datos = Validator::make($entrada, [
            'version' => ['required', 'integer', 'min:0'], 'motivo' => ['required', 'string', 'min:10', 'max:1000'],
            'retirar' => ['required', 'array', 'min:1', 'max:18'], 'retirar.*' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'resolver_agrupaciones' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($referencia, $datos, $usuario): Pedido {
            $pedido = Pedido::whereKey($referencia->id)->lockForUpdate()->firstOrFail();
            if ($pedido->modelo_comercial !== 'unidades' || (int) $pedido->version !== (int) $datos['version']
                || in_array($pedido->estado, ['entregado', 'rechazado', 'cancelado'], true)) {
                throw ValidationException::withMessages(['retiro' => 'El pedido cambió, está cerrado o utiliza reglas históricas. Recarga el detalle; no se convierten precios ni se modifican entregas.']);
            }
            $expediente = DB::table('incidencias_entrega')->where('pedido_id', $pedido->id)->lockForUpdate()->first();
            if ($expediente?->resuelto_en !== null) {
                throw ValidationException::withMessages(['retiro' => 'El expediente está cerrado. Solicita su revisión autorizada antes de modificar el compromiso comercial.']);
            }
            $detalles = $pedido->detalles()->lockForUpdate()->get();
            $salidas = app(AtenderPedidoUnidades::class)->despachadas($pedido->id);
            $cambios = [];
            foreach ($datos['retirar'] as $id => $cantidad) {
                $detalle = $detalles->firstWhere('id', (int) $id);
                if (! ctype_digit((string) $id) || (string) (int) $id !== (string) $id || ! $detalle || (int) $cantidad > (int) $detalle->cantidad - (int) $salidas->get($detalle->id, 0)) {
                    throw ValidationException::withMessages(['retirar' => 'Solo puedes retirar unidades pendientes que pertenecen a este pedido y nunca fueron despachadas.']);
                }
                if ((int) $cantidad > 0) {
                    $cambios[$detalle->id] = (int) $cantidad;
                }
            }
            if ($cambios === []) {
                throw ValidationException::withMessages(['retirar' => 'Indica al menos una unidad faltante que el cliente ya no desea.']);
            }
            $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id] ?? [];
            $grupos = $foto['agrupaciones'] ?? [];
            $reducidos = $detalles->whereIn('id', array_keys($cambios))->pluck('presentacion_producto_id')->all();
            $despachados = app(DistribuirDespachoUnidades::class)->anteriores($pedido->id)['paquetes'];
            if ($datos['resolver_agrupaciones'] ?? false) {
                $grupos = array_values(array_filter($grupos, fn (array $g): bool => isset($despachados[$g['identidad']]) || array_intersect(array_column($g['componentes'], 'sku_id'), $reducidos) === []));
            }
            $lineas = $detalles->map(function ($d) use ($cambios, $salidas): array {
                $cantidad = (int) $d->cantidad - ($cambios[$d->id] ?? 0);

                return ['id' => $d->id, 'presentacion_producto_id' => $d->presentacion_producto_id, 'cantidad' => $cantidad,
                    'cantidad_espera' => min((int) $d->cantidad_espera, max(0, $cantidad - (int) $salidas->get($d->id, 0)))];
            })->all();
            $entrada = ['cliente_id' => $pedido->cliente_id, 'fecha_pedido' => $pedido->fecha_pedido->toDateString(), 'fecha_entrega' => $pedido->fecha_entrega->toDateString(),
                'condicion_pago' => $pedido->condicion_pago, 'observaciones' => $pedido->observaciones, '_version' => $pedido->version,
                'motivo' => $datos['motivo'], 'detalles' => $lineas, 'agrupaciones' => $grupos];
            try {
                $guardado = app(GestionarPedidoUnidades::class)->guardar($entrada, $usuario, $pedido);
            } catch (ValidationException $error) {
                if (isset($error->errors()['agrupaciones'])) {
                    throw ValidationException::withMessages(['agrupaciones' => 'Hay paquetes pendientes afectados. Puedes ajustar su distribución desde Editar pedido, o confirmar que las agrupaciones pendientes de los SKU retirados se desarmen y las unidades restantes queden sueltas. Los paquetes despachados no cambian.']);
                }
                throw $error;
            }
            RegistroAuditoria::registrar('pedido_faltantes_retirados', 'pedido', $pedido->id, ['cantidad' => array_sum($cambios), 'motivo' => $entrada['motivo'], 'campos' => ['retiros_por_linea' => $cambios, 'agrupaciones_pendientes_desarmadas' => (bool) ($datos['resolver_agrupaciones'] ?? false)]]);

            return $guardado;
        }, 3);
    }
}
