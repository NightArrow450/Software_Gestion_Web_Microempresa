<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FiltrosIndicadores
{
    public const APLICABLES = [
        'materiales_resumen' => ['pedido_id', 'producto_id', 'presentacion_id', 'sku', 'insumo_id', 'vinculacion'],
        'saldos_periodo' => ['producto_id', 'presentacion_id', 'sku', 'insumo_id'],
        'comercial' => ['pedido_id', 'producto_id', 'presentacion_id', 'estado', 'modalidad', 'tamano', 'composicion'],
        'composiciones' => ['pedido_id', 'producto_id', 'presentacion_id', 'estado', 'modalidad', 'tamano', 'composicion', 'sku', 'historial'],
        'preparacion' => ['pedido_id', 'producto_id', 'presentacion_id', 'estado', 'modalidad', 'tamano', 'sku'],
        'fisicas' => ['pedido_id', 'producto_id', 'presentacion_id', 'estado', 'modalidad', 'tamano', 'sku'],
        'pendientes' => ['pedido_id', 'producto_id', 'estado', 'modalidad', 'tamano'],
        'situacion_entregas' => ['pedido_id', 'estado', 'presentacion_id', 'sku'],
        'entregas' => ['pedido_id', 'producto_id', 'presentacion_id', 'estado', 'sku'],
        'retornos' => ['pedido_id', 'producto_id', 'presentacion_id', 'estado', 'sku'],
        'aprobaciones' => ['pedido_id', 'producto_id', 'presentacion_id', 'estado', 'sku'],
        'envasados' => ['pedido_id', 'producto_id', 'presentacion_id', 'sku', 'vinculacion'],
        'materiales' => ['pedido_id', 'producto_id', 'presentacion_id', 'sku', 'insumo_id', 'vinculacion'],
        'movimientos' => ['pedido_id', 'producto_id', 'presentacion_id', 'sku', 'insumo_id', 'tipo', 'origen'],
        'inventario' => ['producto_id', 'presentacion_id', 'sku', 'insumo_id'],
    ];

    public static function validar(array $entrada): array
    {
        $reporte = $entrada['reporte'] ?? 'comercial';
        $desde = in_array($reporte, ['inventario', 'preparacion'], true) ? null : now('America/Lima')->startOfMonth()->toDateString();
        $datos = Validator::make($entrada + ['reporte' => $reporte, 'desde' => $desde, 'hasta' => now('America/Lima')->toDateString()], [
            'reporte' => ['required', Rule::in(array_keys(FilasIndicadores::REPORTES))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', ...(! empty($entrada['desde'] ?? $desde) ? ['after_or_equal:desde'] : [])],
            'pedido_id' => ['nullable', 'integer', 'min:1'],
            'producto_id' => ['nullable', 'integer', 'min:1'],
            'presentacion_id' => ['nullable', 'integer', 'min:1'],
            'insumo_id' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', Rule::in(['registrado', 'observado', 'confirmado', 'en_preparacion', 'listo_despacho', ...ControlEntregaPedido::ESTADOS, 'cancelado'])],
            'modalidad' => ['nullable', Rule::in(['unidad', 'empaque'])],
            'tamano' => ['nullable', 'integer', 'between:2,1000'],
            'composicion' => ['prohibited'],
            'sku' => ['nullable', 'string', 'max:50'],
            'tipo' => ['nullable', 'in:entrada,salida,ajuste'],
            'origen' => ['nullable', 'in:manual,despacho,despacho_unidades,envasado,correccion_envasado,retorno_pedido'],
            'vinculacion' => ['nullable', 'in:general,vinculado'],
            'historial' => ['nullable', 'boolean'],
            'economico' => ['nullable', 'boolean'],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'resumen' => ['nullable', 'boolean'],
        ])->validate();
        if ($reporte === 'comercial' && ! empty($datos['sku'])) {
            throw ValidationException::withMessages(['sku' => 'El filtro SKU corresponde al desglose físico; no atribuye el precio completo de un paquete a un aroma.']);
        }
        foreach (array_diff(array_unique(array_merge(...array_values(self::APLICABLES))), self::APLICABLES[$reporte]) as $campo) {
            if (! empty($datos[$campo])) {
                throw ValidationException::withMessages([$campo => 'Este filtro no corresponde al reporte seleccionado.']);
            }
        }

        return array_filter($datos, static fn ($v): bool => $v !== '' && $v !== null) + ['desde' => null, 'economico' => false, 'historial' => false, 'pagina' => 1];
    }

    public static function periodo(Builder $q, string $columna, array $f): Builder
    {
        if ($f['desde'] !== null) {
            $q->where($columna, '>=', $f['desde'].(str_contains($columna, 'fecha_') ? '' : ' 00:00:00'));
        }
        $limite = CarbonImmutable::parse($f['hasta'], 'America/Lima')->addDay()->startOfDay();

        return $q->where($columna, '<', str_contains($columna, 'fecha_') ? $limite->toDateString() : $limite->toDateTimeString());
    }

    public static function pedido(Builder $q, array $f, string $alias = 'pe'): Builder
    {
        foreach (['pedido_id' => 'id', 'estado' => 'estado'] as $campo => $columna) {
            if (! empty($f[$campo])) {
                $q->where($alias.'.'.$columna, $f[$campo]);
            }
        }
        if (! empty($f['_pedidos_ids'])) {
            $q->whereIn($alias.'.id', $f['_pedidos_ids']);
        }

        return $q;
    }

    public static function centimos(string|int|float $valor): int
    {
        return (int) round((float) $valor * 100);
    }
}
