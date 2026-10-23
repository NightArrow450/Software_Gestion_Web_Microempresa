<?php

namespace App\Services;

use App\Models\ConfiguracionEnvasado;
use App\Models\Insumo;
use App\Models\MaterialEnvasado;
use App\Models\PresentacionProducto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConfigurarMaterialesEnvasado
{
    public function guardar(PresentacionProducto $presentacion, array $entrada, ?ConfiguracionEnvasado $configuracion = null): ConfiguracionEnvasado
    {
        $datos = Validator::make($entrada, [
            'nombre' => ['required', 'string', 'max:120'], 'estado' => ['required', 'boolean'], 'version' => ['required', 'integer', 'min:0'],
            'materiales' => ['required', 'array', 'min:1', 'max:30'], 'materiales.*.insumo_id' => ['required', 'integer', 'distinct', 'exists:insumos,id'],
            'materiales.*.funcion' => ['required', Rule::in(['envase', 'etiqueta', 'contratapa', 'tapa', 'manga', 'otro_fisico'])],
            'materiales.*.base_consumo' => ['required', Rule::in(['unidad', 'empaque'])],
            'materiales.*.cantidad_por_unidad' => ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,3'],
            'materiales.*.unidades_por_empaque' => ['nullable', 'integer', 'min:2', 'max:1000'], 'materiales.*.tipo_empaque' => ['nullable', 'string', 'max:50'],
        ])->validate();

        return DB::transaction(function () use ($presentacion, $configuracion, $datos): ConfiguracionEnvasado {
            PresentacionProducto::whereKey($presentacion->id)->lockForUpdate()->firstOrFail();
            if ($configuracion) {
                $configuracion = ConfiguracionEnvasado::whereKey($configuracion->id)->lockForUpdate()->firstOrFail();
                abort_unless($configuracion->presentacion_producto_id === $presentacion->id, 404);
                if ($configuracion->version !== (int) $datos['version']) {
                    throw ValidationException::withMessages(['version' => 'La configuración cambió. Recarga antes de guardar.']);
                }
            }
            if (ConfiguracionEnvasado::where('presentacion_producto_id', $presentacion->id)->where('nombre', $datos['nombre'])->when($configuracion, fn ($q) => $q->whereKeyNot($configuracion->id))->exists()) {
                throw ValidationException::withMessages(['nombre' => 'Ya existe una alternativa con ese nombre.']);
            }
            $insumos = Insumo::whereIn('id', array_column($datos['materiales'], 'insumo_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($datos['materiales'] as $m) {
                $insumo = $insumos->get($m['insumo_id']);
                if (! $insumo || ! $insumo->estado || $insumo->categoria === 'Químicos' || ! in_array($insumo->unidad_medida, ['unidad', 'metro', 'paquete', 'kilogramo'], true)) {
                    throw ValidationException::withMessages(['materiales' => 'Selecciona insumos físicos activos; no se consumen líquidos ni químicos.']);
                }
                if ($m['base_consumo'] === 'empaque' && (empty($m['tipo_empaque']) || empty($m['unidades_por_empaque']))) {
                    throw ValidationException::withMessages(['materiales' => 'Indica el tipo y tamaño del empaque para el consumo futuro por empaque.']);
                }
                if ($m['funcion'] === 'manga' && ($m['base_consumo'] !== 'empaque' || $insumo->unidad_medida !== 'metro')) {
                    throw ValidationException::withMessages(['materiales' => 'Las mangas se configuran en metros por empaque y no se descuentan durante el envasado.']);
                }
                if ($insumo->unidad_medida === 'unidad' && CantidadesEnvasado::milesimas($m['cantidad_por_unidad']) % 1000 !== 0) {
                    throw ValidationException::withMessages(['materiales' => 'Los materiales medidos en unidades requieren consumos enteros.']);
                }
            }
            $configuracion ??= new ConfiguracionEnvasado(['presentacion_producto_id' => $presentacion->id]);
            $configuracion->fill(['nombre' => $datos['nombre'], 'estado' => $datos['estado'], 'version' => $configuracion->exists ? $configuracion->version + 1 : 0])->save();
            $configuracion->materiales()->update(['estado' => false]);
            foreach ($datos['materiales'] as $m) {
                MaterialEnvasado::updateOrCreate(['configuracion_envasado_id' => $configuracion->id, 'insumo_id' => $m['insumo_id']], [
                    'funcion' => $m['funcion'], 'base_consumo' => $m['base_consumo'], 'cantidad_por_unidad' => $m['cantidad_por_unidad'], 'estado' => true,
                    'unidades_por_empaque' => $m['base_consumo'] === 'empaque' ? $m['unidades_por_empaque'] : 0, 'tipo_empaque' => $m['base_consumo'] === 'empaque' ? $m['tipo_empaque'] : null,
                ]);
            }
            RegistroAuditoria::registrar('materiales_envasado_configurados', 'configuracion_envasado', $configuracion->id, ['nombre' => $configuracion->nombre]);

            return $configuracion;
        }, 3);
    }
}
