<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GestionarIncidenciaEntrega
{
    public function __construct(private readonly ControlEntregaPedido $control) {}

    public function ejecutar(Pedido $pedido, array $entrada, User $usuario, string $accion): void
    {
        $permiso = match ($accion) {
            'resolver' => 'cumplimiento.incidencias.resolver', 'reabrir' => 'cumplimiento.incidencias.corregir', default => 'cumplimiento.incidencias.revisar'
        };
        $this->control->autorizar($usuario, $permiso);
        $datos = Validator::make($entrada, ['version' => ['required', 'integer', 'min:0'], 'motivo' => ['required', 'string', 'min:10', 'max:1000'], 'retorno_detalle_id' => [Rule::requiredIf($accion === 'clasificar'), 'nullable', 'integer'], 'aptas' => [Rule::requiredIf($accion === 'clasificar'), 'nullable', 'integer', 'min:0', 'max:10000000'], 'danadas' => [Rule::requiredIf($accion === 'clasificar'), 'nullable', 'integer', 'min:0', 'max:10000000']])->validate();
        DB::transaction(function () use ($pedido, $datos, $usuario, $accion): void {
            $actual = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            if (! in_array($actual->estado, ControlEntregaPedido::ESTADOS, true)) {
                throw ValidationException::withMessages(['estado' => 'El pedido todavía no ha sido despachado.']);
            }
            $incidencia = $this->control->bloquear($actual, (int) $datos['version'], $accion === 'reabrir');
            $resumen = $this->control->resumen($actual, true);
            if ($accion === 'clasificar') {
                $retorno = $resumen['retornos']->firstWhere('id', (int) $datos['retorno_detalle_id']);
                $cantidad = (int) $datos['aptas'] + (int) $datos['danadas'];
                if (! $retorno || $cantidad < 1 || $cantidad > $retorno->en_revision - $retorno->revisadas_aptas - $retorno->revisadas_danadas) {
                    throw ValidationException::withMessages(['cantidad' => 'La clasificación supera las unidades físicas pendientes de revisión.']);
                }
                DB::table('retorno_pedido_detalles')->where('id', $retorno->id)->update(['revisadas_aptas' => $retorno->revisadas_aptas + (int) $datos['aptas'], 'revisadas_danadas' => $retorno->revisadas_danadas + (int) $datos['danadas']]);
            } elseif ($accion === 'resolver') {
                foreach ($resumen['lineas'] as $linea) {
                    if ($linea['pendientes'] !== 0 || $linea['por_retornar'] !== 0 || $linea['por_revisar'] !== 0 || $linea['por_aprobar'] !== 0) {
                        throw ValidationException::withMessages(['resolucion' => 'Quedan cantidades de entrega, retorno, revisión o aprobación pendientes.']);
                    }
                }
                if ($incidencia->estado_revision !== 'revisada') {
                    throw ValidationException::withMessages(['resolucion' => 'Registra la revisión comercial antes de cerrar el expediente.']);
                }
                DB::table('incidencias_entrega')->where('id', $incidencia->id)->update(['resolucion' => $datos['motivo'], 'resuelto_por_id' => $usuario->id, 'resuelto_en' => now()]);
            } elseif ($accion === 'reabrir') {
                if ($incidencia->resuelto_en === null) {
                    throw ValidationException::withMessages(['estado' => 'El expediente ya está abierto.']);
                }
                $datos['resolucion_anterior'] = $incidencia->resolucion;
                DB::table('incidencias_entrega')->where('id', $incidencia->id)->update(['resolucion' => null, 'resuelto_por_id' => null, 'resuelto_en' => null, 'estado_revision' => 'pendiente']);
            } else {
                DB::table('incidencias_entrega')->where('id', $incidencia->id)->update(['estado_revision' => 'revisada']);
            }
            $this->control->evento($incidencia->id, $usuario->id, 'incidencia_'.$accion, $datos['motivo'], $datos);
            $this->control->avanzar($incidencia->id, 'incidencia_'.$accion, ['pedido_id' => $actual->id, 'motivo' => $datos['motivo']]);
        }, 3);
    }
}
