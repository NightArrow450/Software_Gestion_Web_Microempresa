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
        $limiteLineas = collect($entrada['detalles'] ?? [])->contains(fn ($linea): bool => is_array($linea) && ! empty($linea['despacho_componente_id'])) ? 10000 : 18;
        $datos = Validator::make($entrada, [
            'version' => [$completa ? 'nullable' : 'required', 'integer', 'min:0'],
            'ocurrido_en' => ['nullable', 'date', 'before_or_equal:now'],
            'observaciones' => [$rectificar ? 'required' : 'nullable', 'string', ...($rectificar ? ['min:10'] : []), 'max:1000'],
            'detalles' => [$completa ? 'nullable' : 'required', 'array', 'min:1', 'max:'.$limiteLineas],
            'detalles.*.pedido_detalle_id' => ['required', 'integer'],
            'detalles.*.despacho_componente_id' => ['nullable', 'integer', 'min:1'],
            'detalles.*.aceptadas' => ['required', 'integer', 'min:'.($rectificar ? '-10000000' : '0'), 'max:10000000'],
            'detalles.*.rechazadas' => ['required', 'integer', 'min:'.($rectificar ? '-10000000' : '0'), 'max:10000000'],
            'detalles.*.motivo' => ['nullable', 'string', 'min:10', 'max:1000'],
        ])->validate();
        if (! $this->control->disponible()) {
            abort_unless($completa, 503, 'Las entregas parciales requieren aplicar primero las migraciones autorizadas.');
            $observacion = $datos['observaciones'] ?? null;
            $this->transiciones->registrar($pedido, 'despachado', 'entregado', $usuario->id, ['entregado_por_id' => $usuario->id, 'entregado_en' => now(), 'observacion_entrega' => $observacion], $observacion ?? 'Entrega confirmada.');

            return;
        }
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
            if ($resumen['historico_sin_desglose'] || in_array(null, array_column($resumen['lineas'], 'despachadas'), true)) {
                throw ValidationException::withMessages(['detalles' => 'No se puede reconstruir el desglose histórico con certeza. Documenta la incidencia para revisión sin inventar cantidades.']);
            }
            $lineas = $completa ? array_map(fn (array $linea): array => ['pedido_detalle_id' => $linea['id']] + (isset($linea['componente_id']) ? ['despacho_componente_id' => $linea['componente_id']] : []) + ['aceptadas' => $linea['despachadas'], 'rechazadas' => 0], $resumen['lineas']) : $datos['detalles'];
            $cambios = [];
            $vistos = [];
            foreach ($lineas as $indice => $linea) {
                $clave = ControlEntregaPedido::claveFisica((int) $linea['pedido_detalle_id'], isset($linea['despacho_componente_id']) ? (int) $linea['despacho_componente_id'] : null);
                $base = $resumen['lineas'][$clave] ?? null;
                if (isset($vistos[$clave])) {
                    throw ValidationException::withMessages(['detalles' => 'La referencia física está repetida.', 'detalles.'.$vistos[$clave].'.pedido_detalle_id' => 'Esta referencia física está repetida.', 'detalles.'.$indice.'.pedido_detalle_id' => 'Esta referencia física está repetida.']);
                }
                if ($base && $base['id'] !== (int) $linea['pedido_detalle_id']) {
                    throw ValidationException::withMessages(['detalles' => 'La referencia física no pertenece a la línea.']);
                }
                $vistos[$clave] = $indice;
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
                $cambios[] = (isset($base['componente_id']) ? ['despacho_componente_id' => $base['componente_id']] : []) + ['pedido_detalle_id' => $base['id'], 'aceptadas' => (int) $linea['aceptadas'], 'rechazadas' => (int) $linea['rechazadas'], 'motivo' => $linea['motivo'] ?? $datos['observaciones'] ?? null];
            }
            if ($cambios === [] || (array_sum(array_column($cambios, 'aceptadas')) === 0 && array_sum(array_column($cambios, 'rechazadas')) === 0 && mb_strlen(trim($datos['observaciones'] ?? '')) < 10)) {
                throw ValidationException::withMessages(['detalles' => 'Registra cantidades o explica por qué la entrega sigue pendiente (mínimo 10 caracteres).']);
            }
            $ocurridoEn = $this->control->fechaDelHecho($datos['ocurrido_en'] ?? null);
            $id = DB::table('entregas_pedido')->insertGetId(['incidencia_id' => $incidencia->id, 'usuario_id' => $usuario->id, 'tipo' => $rectificar ? 'rectificacion' : 'resultado', 'ocurrido_en' => $ocurridoEn, 'observaciones' => $datos['observaciones'] ?? null, 'created_at' => now()]);
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
            $this->control->evento($incidencia->id, $usuario->id, $rectificar ? 'rectificacion_entrega' : 'resultado_entrega', $datos['observaciones'] ?? 'Registro del resultado; no modifica existencias.', ['entrega_id' => $id, 'detalles' => $cambios, 'ocurrido_en' => $ocurridoEn]);
            $this->control->avanzar($incidencia->id, $rectificar ? 'entrega_rectificada' : 'entrega_registrada', ['pedido_id' => $actual->id, 'motivo' => $datos['observaciones'] ?? null]);
        }, 3);
    }
}
