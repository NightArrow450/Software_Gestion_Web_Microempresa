<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegistrarResultadoEntrega
{
    public function __construct(private readonly ControlEntregaPedido $control, private readonly TransicionesPedido $transiciones) {}

    public function ejecutar(Pedido $pedido, array $entrada, User $usuario, bool $completa = false, bool $rectificar = false): void
    {
        $this->control->autorizar($usuario, $rectificar ? 'cumplimiento.incidencias.corregir' : 'cumplimiento.entregar');
        $datos = Validator::make($entrada, [
            'version' => [$completa ? 'nullable' : 'required', 'integer', 'min:0'],
            'ocurrido_en' => ['nullable', 'date', 'before_or_equal:now'],
            'observaciones' => [$rectificar ? 'required' : 'nullable', 'string', ...($rectificar ? ['min:10'] : []), 'max:1000'],
            'detalles' => [$completa ? 'nullable' : 'required', 'array', 'min:1', 'max:18'],
            'detalles.*.pedido_detalle_id' => ['required', 'integer', 'distinct'],
            'detalles.*.aceptadas' => ['required', 'integer', 'min:'.($rectificar ? '-10000000' : '0'), 'max:10000000'],
            'detalles.*.rechazadas' => ['required', 'integer', 'min:'.($rectificar ? '-10000000' : '0'), 'max:10000000'],
            'detalles.*.motivo' => ['nullable', 'string', 'min:10', 'max:1000'],
        ])->validate();
        DB::transaction(function () use ($pedido, $datos, $usuario, $completa, $rectificar): void {
            $actual = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            if (! in_array($actual->estado, $rectificar ? ControlEntregaPedido::ESTADOS : ['despachado', 'entrega_pendiente'], true) || ($completa && $actual->estado !== 'despachado')) {
                throw ValidationException::withMessages(['estado' => 'El pedido no admite este resultado de entrega.']);
            }
            $incidencia = $this->control->bloquear($actual, isset($datos['version']) ? (int) $datos['version'] : null);
            if ($completa && DB::table('entregas_pedido')->where('incidencia_id', $incidencia->id)->exists()) {
                throw ValidationException::withMessages(['estado' => 'Ya hay resultados por línea. Registra únicamente las unidades pendientes.']);
            }
            $resumen = $this->control->resumen($actual, true);
            $lineas = $completa ? array_map(fn (array $linea): array => ['pedido_detalle_id' => $linea['id'], 'aceptadas' => $linea['despachadas'], 'rechazadas' => 0], $resumen['lineas']) : $datos['detalles'];
            $cambios = [];
            foreach ($lineas as $linea) {
                $base = $resumen['lineas'][$linea['pedido_detalle_id']] ?? null;
                if (! $base || $base['despachadas'] === null) {
                    throw ValidationException::withMessages(['detalles' => 'La línea no pertenece al pedido o su tamaño histórico no puede comprobarse. Solicita revisión.']);
                }
                $aceptadas = $base['aceptadas'] + (int) $linea['aceptadas'];
                $rechazadas = $base['rechazadas'] + (int) $linea['rechazadas'];
                if ($aceptadas < 0 || $rechazadas < $base['retornadas'] + $base['no_recuperadas'] || $aceptadas + $rechazadas > $base['despachadas']) {
                    throw ValidationException::withMessages(['detalles' => 'Las cantidades exceden lo despachado o afectan rechazos con retornos ya registrados.']);
                }
                if ((int) $linea['rechazadas'] > 0 && mb_strlen(trim($linea['motivo'] ?? $datos['observaciones'] ?? '')) < 10) {
                    throw ValidationException::withMessages(['motivo' => 'Describe el rechazo con al menos 10 caracteres, sin atribuir responsabilidades no comprobadas.']);
                }
                $cambios[] = ['pedido_detalle_id' => $base['id'], 'aceptadas' => (int) $linea['aceptadas'], 'rechazadas' => (int) $linea['rechazadas'], 'motivo' => $linea['motivo'] ?? $datos['observaciones'] ?? null];
            }
            if ($cambios === [] || (array_sum(array_column($cambios, 'aceptadas')) === 0 && array_sum(array_column($cambios, 'rechazadas')) === 0 && mb_strlen(trim($datos['observaciones'] ?? '')) < 10)) {
                throw ValidationException::withMessages(['detalles' => 'Registra cantidades o explica por qué la entrega sigue pendiente (mínimo 10 caracteres).']);
            }
            $id = DB::table('entregas_pedido')->insertGetId(['incidencia_id' => $incidencia->id, 'usuario_id' => $usuario->id, 'tipo' => $rectificar ? 'rectificacion' : 'resultado', 'ocurrido_en' => $datos['ocurrido_en'] ?? now(), 'observaciones' => $datos['observaciones'] ?? null, 'created_at' => now()]);
            foreach ($cambios as $cambio) {
                DB::table('entrega_pedido_detalles')->insert($cambio + ['entrega_id' => $id]);
            }
            $final = $this->control->resumen($actual, true)['lineas'];
            $pendientes = array_sum(array_column($final, 'pendientes'));
            $rechazadas = array_sum(array_column($final, 'rechazadas'));
            $aceptadas = array_sum(array_column($final, 'aceptadas'));
            $estado = $pendientes > 0 ? 'entrega_pendiente' : ($rechazadas === 0 ? 'entregado' : ($aceptadas === 0 ? 'rechazado' : 'entrega_parcial'));
            $adicionales = $estado === 'entregado' ? ['entregado_por_id' => $actual->entregado_por_id ?? $usuario->id, 'entregado_en' => $actual->entregado_en ?? now(), 'observacion_entrega' => $datos['observaciones'] ?? null] : [];
            $this->transiciones->registrar($actual, $actual->estado, $estado, $usuario->id, $adicionales, $datos['observaciones'] ?? ($rectificar ? 'Rectificación autorizada de entrega.' : 'Resultado de entrega por líneas.'));
            $this->control->evento($incidencia->id, $usuario->id, $rectificar ? 'rectificacion_entrega' : 'resultado_entrega', $datos['observaciones'] ?? 'Registro del resultado; no modifica existencias.', ['entrega_id' => $id, 'detalles' => $cambios]);
            $this->control->avanzar($incidencia->id, $rectificar ? 'entrega_rectificada' : 'entrega_registrada');
        }, 3);
    }
}
