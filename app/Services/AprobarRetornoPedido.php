<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AprobarRetornoPedido
{
    public function __construct(private readonly ControlEntregaPedido $control) {}

    public function ejecutar(Pedido $pedido, array $entrada, User $usuario): void
    {
        $this->control->autorizar($usuario, 'cumplimiento.retornos.aprobar');
        $datos = Validator::make($entrada, ['version' => ['required', 'integer', 'min:0'], 'retorno_detalle_id' => ['required', 'integer'], 'cantidad' => ['required', 'integer', 'min:1', 'max:10000000'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']])->validate();
        DB::transaction(function () use ($pedido, $datos, $usuario): void {
            $actual = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            if (! in_array($actual->estado, ControlEntregaPedido::ESTADOS, true)) {
                throw ValidationException::withMessages(['estado' => 'El pedido todavía no ha sido despachado.']);
            }
            $incidencia = $this->control->bloquear($actual, (int) $datos['version']);
            $resumen = $this->control->resumen($actual, true);
            $retorno = $resumen['retornos']->firstWhere('id', (int) $datos['retorno_detalle_id']);
            $cantidad = (int) $datos['cantidad'];
            $base = $retorno ? ($resumen['lineas'][$retorno->pedido_detalle_id] ?? null) : null;
            if (! $retorno || ! $base || ! $base['verificada'] || $cantidad > $retorno->aptas + $retorno->revisadas_aptas - $retorno->reincorporadas) {
                throw ValidationException::withMessages(['cantidad' => 'No hay suficientes unidades aptas pendientes o el despacho histórico no está verificado.']);
            }
            $presentacion = PresentacionProducto::whereKey($base['presentacion_id'])->lockForUpdate()->first();
            $inventario = Inventario::where('presentacion_producto_id', $base['presentacion_id'])->lockForUpdate()->first();
            if (! $presentacion || ! $inventario || (int) $inventario->cantidad_actual + $cantidad > 4294967295) {
                throw ValidationException::withMessages(['inventario' => 'No se puede reincorporar: revisa la presentación, su inventario y el límite de existencias.']);
            }
            $anterior = (int) $inventario->cantidad_actual;
            $eventoId = $this->control->evento($incidencia->id, $usuario->id, 'retorno_aprobado', $datos['motivo'], ['retorno_detalle_id' => $retorno->id, 'pedido_detalle_id' => $base['id'], 'cantidad' => $cantidad, 'stock_anterior' => $anterior, 'stock_nuevo' => $anterior + $cantidad]);
            $inventario->update(['cantidad_actual' => $anterior + $cantidad]);
            MovimientoInventario::create(['presentacion_producto_id' => $presentacion->id, 'usuario_id' => $usuario->id, 'pedido_id' => null, 'origen_tipo' => 'retorno_pedido', 'origen_id' => (string) $eventoId, 'tipo' => 'entrada', 'cantidad' => $cantidad, 'stock_anterior' => $anterior, 'stock_nuevo' => $anterior + $cantidad, 'motivo' => 'Retorno físico aprobado del pedido '.$actual->numero.': '.$datos['motivo']]);
            DB::table('retorno_pedido_detalles')->where('id', $retorno->id)->increment('reincorporadas', $cantidad);
            $this->control->avanzar($incidencia->id, 'retorno_aprobado', ['pedido_id' => $actual->id, 'cantidad' => $cantidad, 'stock_anterior' => $anterior, 'stock_nuevo' => $anterior + $cantidad, 'motivo' => $datos['motivo']]);
        }, 3);
    }
}
