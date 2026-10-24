<?php

namespace App\Services;

use App\Models\ConfiguracionEnvasado;
use App\Models\Envasado;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrarEnvasado
{
    public function previsualizar(array $entrada): array
    {
        return DB::transaction(fn (): array => $this->preparar($entrada));
    }

    public function ejecutar(array $entrada, User $usuario): Envasado
    {
        return DB::transaction(function () use ($entrada, $usuario): Envasado {
            $plan = $this->preparar($entrada);
            $inventario = $plan['inventario'];
            $presentacion = $plan['presentacion'];
            $envasado = Envasado::create(['numero' => (string) Str::ulid(), 'presentacion_producto_id' => $presentacion->id, 'configuracion_envasado_id' => $plan['configuracion']->id, 'configuracion_nombre' => $plan['configuracion']->nombre, 'usuario_id' => $usuario->id, 'pedido_id' => $plan['pedido']?->id, 'cantidad' => $plan['cantidad'], 'observaciones' => $plan['observaciones'], 'presentacion_historica' => ['sku' => $presentacion->codigo_sku, 'producto' => $presentacion->producto->nombre, 'variante' => $presentacion->variante?->valor, 'contenido' => $presentacion->contenido, 'unidad_medida' => $presentacion->unidad_medida, 'tipo_envase' => $presentacion->tipo_envase]]);
            foreach ($plan['consumos'] as $consumo) {
                $insumo = $consumo['insumo'];
                $anterior = CantidadesEnvasado::milesimas($insumo->cantidad_actual);
                $nuevo = $anterior - $consumo['milesimas'];
                $insumo->update(['cantidad_actual' => CantidadesEnvasado::decimal($nuevo)]);
                $envasado->consumos()->create(['insumo_id' => $insumo->id, 'nombre' => $insumo->nombre, 'codigo' => $insumo->codigo, 'unidad_medida' => $insumo->unidad_medida, 'funcion' => $consumo['material']->funcion, 'cantidad_por_unidad' => $consumo['material']->cantidad_por_unidad, 'cantidad' => CantidadesEnvasado::decimal($consumo['milesimas'])]);
                MovimientoInventario::create(['insumo_id' => $insumo->id, 'usuario_id' => $usuario->id, 'origen_tipo' => 'envasado', 'origen_id' => (string) $envasado->id, 'tipo' => 'salida', 'cantidad' => CantidadesEnvasado::decimal($consumo['milesimas']), 'stock_anterior' => CantidadesEnvasado::decimal($anterior), 'stock_nuevo' => CantidadesEnvasado::decimal($nuevo), 'motivo' => 'Material utilizado en el envasado '.$envasado->numero.'.']);
            }
            $anterior = $inventario->cantidad_actual;
            $nuevo = $anterior + $plan['cantidad'];
            $inventario->fill(['cantidad_actual' => $nuevo])->save();
            MovimientoInventario::create(['presentacion_producto_id' => $presentacion->id, 'usuario_id' => $usuario->id, 'origen_tipo' => 'envasado', 'origen_id' => (string) $envasado->id, 'tipo' => 'entrada', 'cantidad' => $plan['cantidad'], 'stock_anterior' => $anterior, 'stock_nuevo' => $nuevo, 'motivo' => 'Producto terminado del envasado '.$envasado->numero.'.']);
            RegistroAuditoria::registrar('envasado_registrado', 'envasado', $envasado->id, ['cantidad' => $plan['cantidad'], 'pedido_id' => $envasado->pedido_id]);

            return $envasado;
        }, 3);
    }

    private function preparar(array $entrada): array
    {
        $datos = Validator::make($entrada, ['presentacion_producto_id' => ['required', 'integer', 'exists:presentaciones_producto,id'], 'configuracion_envasado_id' => ['required', 'integer', 'exists:configuraciones_envasado,id'], 'version_configuracion' => ['required', 'integer', 'min:0'], 'cantidad' => ['required', 'integer', 'min:1', 'max:1000000'], 'pedido_id' => ['nullable', 'integer', 'exists:pedidos,id'], 'observaciones' => ['nullable', 'string', 'max:1000']])->validate();
        $pedido = ! empty($datos['pedido_id']) ? Pedido::whereKey($datos['pedido_id'])->lockForUpdate()->firstOrFail() : null;
        if ($pedido && (! in_array($pedido->estado, ['confirmado', 'en_preparacion'], true) || ! app(GestionarSurtidos::class)->lineasFisicas($pedido)->contains('presentacion_producto_id', (int) $datos['presentacion_producto_id']))) {
            throw ValidationException::withMessages(['pedido_id' => 'El pedido debe estar confirmado o en preparación y contener la presentación seleccionada.']);
        }
        $presentacion = PresentacionProducto::with(['producto', 'variante'])->whereKey($datos['presentacion_producto_id'])->lockForUpdate()->firstOrFail();
        if (! $presentacion->estado || ! $presentacion->producto->estado || ($presentacion->variante && ! $presentacion->variante->estado)) {
            throw ValidationException::withMessages(['presentacion_producto_id' => 'Selecciona una presentación activa.']);
        }
        $configuracion = ConfiguracionEnvasado::whereKey($datos['configuracion_envasado_id'])->lockForUpdate()->firstOrFail();
        if (! $configuracion->estado || $configuracion->presentacion_producto_id !== $presentacion->id || $configuracion->version !== (int) $datos['version_configuracion']) {
            throw ValidationException::withMessages(['configuracion_envasado_id' => 'La configuración no corresponde, está inactiva o cambió. Vuelve a consultar los consumos antes de confirmar.']);
        }
        $materiales = $configuracion->materiales()->where('estado', true)->where('base_consumo', 'unidad')->orderBy('insumo_id')->lockForUpdate()->get();
        if ($materiales->isEmpty()) {
            throw ValidationException::withMessages(['configuracion_envasado_id' => 'Falta configurar materiales válidos por unidad para esta presentación.']);
        }
        $inventario = Inventario::where('presentacion_producto_id', $presentacion->id)->lockForUpdate()->first();
        if (! $inventario) {
            $inventario = new Inventario(['presentacion_producto_id' => $presentacion->id, 'cantidad_actual' => 0]);
        }
        $cantidad = (int) $datos['cantidad'];
        if ($inventario->cantidad_actual + $cantidad > 4294967295) {
            throw ValidationException::withMessages(['cantidad' => 'La existencia máxima de producto terminado fue superada.']);
        }
        $insumos = Insumo::whereIn('id', $materiales->pluck('insumo_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $consumos = [];
        $faltantes = [];
        foreach ($materiales as $material) {
            $insumo = $insumos->get($material->insumo_id);
            if (! $insumo || ! $insumo->estado || $insumo->categoria === 'Químicos' || ! in_array($insumo->unidad_medida, ['unidad', 'metro', 'paquete', 'kilogramo'], true) || $material->funcion === 'manga') {
                throw ValidationException::withMessages(['materiales' => 'Hay materiales inactivos o no válidos. Revisa la configuración.']);
            }
            $coeficiente = CantidadesEnvasado::milesimas($material->cantidad_por_unidad);
            if ($coeficiente < 1 || ($insumo->unidad_medida === 'unidad' && $coeficiente % 1000 !== 0)) {
                throw ValidationException::withMessages(['materiales' => 'El consumo configurado no es válido para la unidad del insumo.']);
            }
            $requerido = $coeficiente * $cantidad;
            if ($requerido > 1000000000000) {
                throw ValidationException::withMessages(['cantidad' => 'El consumo supera la cantidad máxima de inventario.']);
            }
            if (CantidadesEnvasado::milesimas($insumo->cantidad_actual) < $requerido) {
                $faltantes[] = $insumo->nombre.': se necesitan '.CantidadesEnvasado::decimal($requerido).' '.$insumo->unidad_medida.' y hay '.$insumo->cantidad_actual.'.';
            }
            $consumos[] = ['insumo' => $insumo, 'material' => $material, 'milesimas' => $requerido];
        }
        if ($faltantes !== []) {
            throw ValidationException::withMessages(['materiales' => $faltantes]);
        }

        return ['pedido' => $pedido, 'presentacion' => $presentacion, 'configuracion' => $configuracion, 'inventario' => $inventario, 'cantidad' => $cantidad, 'consumos' => $consumos, 'observaciones' => $datos['observaciones'] ?? null];
    }
}
