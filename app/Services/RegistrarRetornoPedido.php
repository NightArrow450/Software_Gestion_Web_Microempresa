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
        abort_unless($this->control->disponible(), 503, 'Los retornos requieren las migraciones autorizadas.');
        $limiteLineas = collect($entrada['detalles'] ?? [])->contains(fn ($linea): bool => is_array($linea) && ! empty($linea['despacho_componente_id'])) ? 10000 : 18;
        $reglas = ['version' => ['required', 'integer', 'min:0'], 'ocurrido_en' => ['nullable', 'date', 'before_or_equal:now'], 'observaciones' => ['required', 'string', 'min:10', 'max:1000'], 'detalles' => ['required', 'array', 'min:1', 'max:'.$limiteLineas], 'detalles.*.pedido_detalle_id' => ['required', 'integer']];
        $reglas['detalles.*.despacho_componente_id'] = ['nullable', 'integer', 'min:1'];
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
            $vistos = [];
            foreach ($datos['detalles'] as $linea) {
                $clave = ControlEntregaPedido::claveFisica((int) $linea['pedido_detalle_id'], isset($linea['despacho_componente_id']) ? (int) $linea['despacho_componente_id'] : null);
                $base = $resumen['lineas'][$clave] ?? null;
                if (isset($vistos[$clave]) || ($base && $base['id'] !== (int) $linea['pedido_detalle_id'])) {
                    throw ValidationException::withMessages(['detalles' => 'La referencia física está repetida o no pertenece a la línea.']);
                }
                $vistos[$clave] = true;
                $cantidad = (int) $linea['aptas'] + (int) $linea['danadas'] + (int) $linea['en_revision'] + (int) $linea['no_recuperadas'];
                if (! $base || $cantidad > $base['por_retornar']) {
                    throw ValidationException::withMessages(['detalles' => 'La línea no pertenece al pedido o el retorno supera los rechazos pendientes.']);
                }
                if ($cantidad > 0) {
                    $filas[] = array_map('intval', array_filter($linea, fn ($v): bool => $v !== null));
                }
            }
            if ($filas === []) {
                throw ValidationException::withMessages(['detalles' => 'Indica al menos una unidad recibida o declarada no recuperada.']);
            }
            $ocurridoEn = $this->control->fechaDelHecho($datos['ocurrido_en'] ?? null);
            $id = DB::table('retornos_pedido')->insertGetId(['incidencia_id' => $incidencia->id, 'usuario_id' => $usuario->id, 'ocurrido_en' => $ocurridoEn, 'observaciones' => $datos['observaciones'], 'created_at' => now()]);
            foreach ($filas as $fila) {
                DB::table('retorno_pedido_detalles')->insert(array_intersect_key($fila, array_flip(['pedido_detalle_id', 'despacho_componente_id', 'aptas', 'danadas', 'en_revision', 'no_recuperadas'])) + ['retorno_id' => $id]);
            }
            $this->control->evento($incidencia->id, $usuario->id, 'retorno_registrado', $datos['observaciones'], ['retorno_id' => $id, 'detalles' => $filas, 'ocurrido_en' => $ocurridoEn]);
            $this->control->avanzar($incidencia->id, 'retorno_registrado', ['pedido_id' => $actual->id, 'motivo' => $datos['observaciones']]);
        }, 3);
    }
}
