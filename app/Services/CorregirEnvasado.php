<?php

namespace App\Services;

use App\Models\Envasado;
use App\Models\EnvasadoCorreccion;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\PresentacionProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CorregirEnvasado
{
    public function previsualizar(Envasado $envasado, array $entrada): array
    {
        return DB::transaction(fn (): array => $this->preparar($envasado, $entrada));
    }

    public function ejecutar(Envasado $envasado, array $entrada, User $usuario): EnvasadoCorreccion
    {
        return DB::transaction(function () use ($envasado, $entrada, $usuario): EnvasadoCorreccion {
            $plan = $this->preparar($envasado, $entrada);
            $envasado = $plan['envasado'];
            $inventario = $plan['inventario'];
            $correccion = EnvasadoCorreccion::create(['envasado_id' => $envasado->id, 'usuario_id' => $usuario->id, 'clasificacion' => $plan['datos']['clasificacion'], 'cantidad' => $plan['cantidad'], 'motivo' => $plan['datos']['motivo']]);
            $anterior = $inventario->cantidad_actual;
            $nuevo = $anterior - $plan['cantidad'];
            $inventario->update(['cantidad_actual' => $nuevo]);
            MovimientoInventario::create(['presentacion_producto_id' => $envasado->presentacion_producto_id, 'usuario_id' => $usuario->id, 'origen_tipo' => 'correccion_envasado', 'origen_id' => (string) $correccion->id, 'tipo' => 'salida', 'cantidad' => $plan['cantidad'], 'stock_anterior' => $anterior, 'stock_nuevo' => $nuevo, 'motivo' => 'Compensación del envasado '.$envasado->numero.': '.$plan['datos']['motivo']]);
            $auditoria = [];
            foreach ($plan['materiales'] as $material) {
                $insumo = $material['insumo'];
                $devolver = $material['devuelta'];
                $afectada = $material['afectada'];
                $perdida = $plan['datos']['clasificacion'] === 'perdida_fisica' ? $afectada - $devolver : 0;
                $correccion->materiales()->create(['envasado_consumo_id' => $material['consumo']->id, 'cantidad_afectada' => CantidadesEnvasado::decimal($afectada), 'cantidad_devuelta' => CantidadesEnvasado::decimal($devolver), 'cantidad_perdida' => CantidadesEnvasado::decimal($perdida)]);
                if ($devolver > 0) {
                    $anterior = CantidadesEnvasado::milesimas($insumo->cantidad_actual);
                    $nuevo = $anterior + $devolver;
                    $insumo->update(['cantidad_actual' => CantidadesEnvasado::decimal($nuevo)]);
                    MovimientoInventario::create(['insumo_id' => $insumo->id, 'usuario_id' => $usuario->id, 'origen_tipo' => 'correccion_envasado', 'origen_id' => (string) $correccion->id, 'tipo' => 'entrada', 'cantidad' => CantidadesEnvasado::decimal($devolver), 'stock_anterior' => CantidadesEnvasado::decimal($anterior), 'stock_nuevo' => CantidadesEnvasado::decimal($nuevo), 'motivo' => 'Material declarado no utilizado o recuperado del envasado '.$envasado->numero.'. '.$plan['datos']['motivo']]);
                }
                $auditoria[] = ['insumo_id' => $insumo->id, 'afectada' => CantidadesEnvasado::decimal($afectada), 'devuelta' => CantidadesEnvasado::decimal($devolver), 'perdida' => CantidadesEnvasado::decimal($perdida)];
            }
            $revertidas = $envasado->unidades_revertidas + $plan['cantidad'];
            $envasado->update(['unidades_revertidas' => $revertidas, 'estado' => $revertidas === $envasado->cantidad ? 'anulado' : 'corregido']);
            RegistroAuditoria::registrar('envasado_corregido', 'envasado_correccion', $correccion->id, ['envasado_id' => $envasado->id, 'clasificacion' => $plan['datos']['clasificacion'], 'cantidad' => $plan['cantidad'], 'motivo' => $plan['datos']['motivo'], 'materiales' => $auditoria]);

            return $correccion;
        }, 3);
    }

    private function preparar(Envasado $envasado, array $entrada): array
    {
        $datos = Validator::make($entrada, ['clasificacion' => ['required', Rule::in(['error_administrativo', 'perdida_fisica'])], 'cantidad' => ['required', 'integer', 'min:1', 'max:1000000'], 'revertidas_previas' => ['required', 'integer', 'min:0'], 'motivo' => ['required', 'string', 'min:10', 'max:1000'], 'devoluciones' => ['required', 'array'], 'devoluciones.*' => ['required', 'numeric', 'min:0', 'max:1000000000', 'decimal:0,3']])->validate();
        $envasado = Envasado::whereKey($envasado->id)->lockForUpdate()->firstOrFail();
        $cantidad = (int) $datos['cantidad'];
        if ($envasado->unidades_revertidas !== (int) $datos['revertidas_previas'] || $envasado->estado === 'anulado' || $cantidad > $envasado->cantidad - $envasado->unidades_revertidas) {
            throw ValidationException::withMessages(['cantidad' => 'El envasado cambió o la cantidad supera sus unidades pendientes de revertir. Recarga el detalle.']);
        }
        PresentacionProducto::whereKey($envasado->presentacion_producto_id)->lockForUpdate()->firstOrFail();
        $inventario = Inventario::where('presentacion_producto_id', $envasado->presentacion_producto_id)->lockForUpdate()->first();
        if (! $inventario || $inventario->cantidad_actual < $cantidad) {
            throw ValidationException::withMessages(['cantidad' => 'El stock disponible actual es '.($inventario->cantidad_actual ?? 0).' y se necesita revertir '.$cantidad.'. No se identifica a qué envasado pertenecen los productos despachados.']);
        }
        $consumos = $envasado->consumos()->orderBy('insumo_id')->get();
        $claves = array_map('strval', array_keys($datos['devoluciones']));
        $esperadas = array_map('strval', $consumos->pluck('id')->all());
        sort($claves);
        sort($esperadas);
        if ($claves !== $esperadas) {
            throw ValidationException::withMessages(['devoluciones' => 'Declara explícitamente la devolución de cada material histórico, incluso cuando sea cero.']);
        }
        $insumos = Insumo::whereIn('id', $consumos->pluck('insumo_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $materiales = [];
        foreach ($consumos as $consumo) {
            $insumo = $insumos->get($consumo->insumo_id);
            $afectada = CantidadesEnvasado::milesimas($consumo->cantidad_por_unidad) * $cantidad;
            $devuelta = CantidadesEnvasado::milesimas($datos['devoluciones'][$consumo->id]);
            if ($devuelta > $afectada || ($consumo->unidad_medida === 'unidad' && $devuelta % 1000 !== 0)) {
                throw ValidationException::withMessages(['devoluciones' => 'La devolución de '.$consumo->nombre.' debe respetar su unidad y no superar '.CantidadesEnvasado::decimal($afectada).'.']);
            }
            if (! $insumo || $insumo->unidad_medida !== $consumo->unidad_medida) {
                throw ValidationException::withMessages(['devoluciones' => 'La unidad del insumo cambió respecto al consumo histórico; no puede compensarse automáticamente.']);
            }
            if (CantidadesEnvasado::milesimas($insumo->cantidad_actual) + $devuelta > 1000000000000) {
                throw ValidationException::withMessages(['devoluciones' => 'La devolución supera la existencia máxima del insumo.']);
            }
            $materiales[] = ['insumo' => $insumo, 'consumo' => $consumo, 'afectada' => $afectada, 'devuelta' => $devuelta];
        }

        return compact('datos', 'envasado', 'cantidad', 'inventario', 'materiales');
    }
}
