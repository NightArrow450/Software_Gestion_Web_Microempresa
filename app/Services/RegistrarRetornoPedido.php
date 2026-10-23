<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegistrarRetornoPedido
{
    public function __construct(private readonly ControlEntregaPedido $control) {}

    public function ejecutar(Pedido $pedido, array $entrada, User $usuario): void
    {
        $this->control->autorizar($usuario, 'cumplimiento.retornos.registrar');
        $reglas = ['version' => ['required', 'integer', 'min:0'], 'ocurrido_en' => ['nullable', 'date', 'before_or_equal:now'], 'observaciones' => ['required', 'string', 'min:10', 'max:1000'], 'detalles' => ['required', 'array', 'min:1', 'max:18'], 'detalles.*.pedido_detalle_id' => ['required', 'integer', 'distinct']];
        foreach (['aptas', 'danadas', 'en_revision', 'no_recuperadas'] as $campo) {
            $reglas['detalles.*.'.$campo] = ['required', 'integer', 'min:0', 'max:10000000'];
        }
        $datos = Validator::make($entrada, $reglas)->validate();
        DB::transaction(function () use ($pedido, $datos, $usuario): void {
            $actual = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            if (! in_array($actual->estado, ControlEntregaPedido::ESTADOS, true)) {
                throw ValidationException::withMessages(['estado' => 'Solo se registran retornos de pedidos ya despachados.']);
            }
            $incidencia = $this->control->bloquear($actual, (int) $datos['version']);
            $resumen = $this->control->resumen($actual, true);
            $filas = [];
            foreach ($datos['detalles'] as $linea) {
                $base = $resumen['lineas'][$linea['pedido_detalle_id']] ?? null;
                $cantidad = (int) $linea['aptas'] + (int) $linea['danadas'] + (int) $linea['en_revision'] + (int) $linea['no_recuperadas'];
                if (! $base || $cantidad > $base['por_retornar']) {
                    throw ValidationException::withMessages(['detalles' => 'La línea no pertenece al pedido o el retorno supera los rechazos pendientes.']);
                }
                if ($cantidad > 0) {
                    $filas[] = array_map('intval', $linea);
                }
            }
            if ($filas === []) {
                throw ValidationException::withMessages(['detalles' => 'Indica al menos una unidad recibida o declarada no recuperada.']);
            }
            $ocurridoEn = $this->control->fechaDelHecho($datos['ocurrido_en'] ?? null);
            $id = DB::table('retornos_pedido')->insertGetId(['incidencia_id' => $incidencia->id, 'usuario_id' => $usuario->id, 'ocurrido_en' => $ocurridoEn, 'observaciones' => $datos['observaciones'], 'created_at' => now()]);
            foreach ($filas as $fila) {
                DB::table('retorno_pedido_detalles')->insert(array_intersect_key($fila, array_flip(['pedido_detalle_id', 'aptas', 'danadas', 'en_revision', 'no_recuperadas'])) + ['retorno_id' => $id]);
            }
            $this->control->evento($incidencia->id, $usuario->id, 'retorno_registrado', $datos['observaciones'], ['retorno_id' => $id, 'detalles' => $filas, 'ocurrido_en' => $ocurridoEn]);
            $this->control->avanzar($incidencia->id, 'retorno_registrado', ['pedido_id' => $actual->id, 'motivo' => $datos['observaciones']]);
        }, 3);
    }
}
