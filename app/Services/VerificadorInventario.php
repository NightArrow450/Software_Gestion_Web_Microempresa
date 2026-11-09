<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class VerificadorInventario
{
    public function revisar(): array
    {
        return DB::transaction(function (): array {
            $reporte = ['sin_historial' => [], 'saldo_inicial' => [], 'consistentes' => [], 'inconsistencias' => [], 'huerfanos' => []];
            foreach (['producto', 'insumo'] as $tipo) {
                $columna = $tipo === 'producto' ? 'presentacion_producto_id' : 'insumo_id';
                $items = $tipo === 'producto' ? DB::table('inventarios')->get()->keyBy('presentacion_producto_id') : DB::table('insumos')->get()->keyBy('id');
                $cadenas = [];
                $movimientos = DB::table('movimientos_inventario')->whereNotNull($columna)
                    ->select(['id', $columna, 'stock_anterior', 'stock_nuevo'])->lazyById(500);
                foreach ($movimientos as $movimiento) {
                    $id = $movimiento->{$columna};
                    if (! isset($cadenas[$id])) {
                        $base = $this->milesimas($movimiento->stock_anterior);
                        $cadenas[$id] = ['base' => $base, 'esperado' => $base, 'errores' => []];
                    }
                    if ($this->milesimas($movimiento->stock_anterior) !== $cadenas[$id]['esperado']) {
                        $cadenas[$id]['errores'][] = ['movimiento_id' => $movimiento->id, 'problema' => 'Cadena de saldos rota'];
                    }
                    if ($this->milesimas($movimiento->stock_nuevo) < 0) {
                        $cadenas[$id]['errores'][] = ['movimiento_id' => $movimiento->id, 'problema' => 'Saldo histórico negativo'];
                    }
                    $cadenas[$id]['esperado'] = $this->milesimas($movimiento->stock_nuevo);
                }
                foreach ($items->keys()->merge(array_keys($cadenas))->unique() as $id) {
                    $item = $items->get($id);
                    $dato = ['tipo' => $tipo, 'id' => (int) $id, 'cantidad_actual' => $item?->cantidad_actual];
                    if (! isset($cadenas[$id])) {
                        $reporte['sin_historial'][] = $dato + ['clasificacion' => 'Sin historial / saldo inicial'];

                        continue;
                    }
                    $base = $cadenas[$id]['base'];
                    $esperado = $cadenas[$id]['esperado'];
                    $errores = $cadenas[$id]['errores'];
                    if (! $item || $this->milesimas($item->cantidad_actual) !== $esperado) {
                        $errores[] = ['problema' => 'El saldo actual no coincide con el último movimiento'];
                    }
                    $dato += ['saldo_inicial' => $base / 1000, 'saldo_final_historial' => $esperado / 1000];
                    if ($errores !== []) {
                        $reporte['inconsistencias'][] = $dato + ['problemas' => $errores];
                    } elseif ($base !== 0) {
                        $reporte['saldo_inicial'][] = $dato + ['clasificacion' => 'Diferencia explicable por saldo inicial o historial previo'];
                    } else {
                        $reporte['consistentes'][] = $dato;
                    }
                }
            }
            $reporte['huerfanos'] = DB::table('movimientos_inventario as m')
                ->leftJoin('presentaciones_producto as p', 'p.id', '=', 'm.presentacion_producto_id')
                ->leftJoin('insumos as i', 'i.id', '=', 'm.insumo_id')
                ->leftJoin('users as u', 'u.id', '=', 'm.usuario_id')
                ->leftJoin('pedidos as pe', 'pe.id', '=', 'm.pedido_id')
                ->where(function ($query) {
                    $query->whereRaw('(m.presentacion_producto_id IS NOT NULL AND p.id IS NULL) OR (m.insumo_id IS NOT NULL AND i.id IS NULL) OR u.id IS NULL OR (m.pedido_id IS NOT NULL AND pe.id IS NULL) OR (m.presentacion_producto_id IS NULL AND m.insumo_id IS NULL)');
                })->pluck('m.id')->all();
            $reporte['correcto'] = $reporte['inconsistencias'] === [] && $reporte['huerfanos'] === [];

            return $reporte;
        });
    }

    private function milesimas(int|float|string $cantidad): int
    {
        return (int) round((float) $cantidad * 1000);
    }
}
