<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PresentacionProducto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Lecturas físicas compartidas; no consulta configuraciones ni composiciones comerciales. */
class LecturaPedido
{
    private ?bool $tieneRetiroLogico = null;

    public function admiteRetiroLogico(): bool
    {
        return $this->tieneRetiroLogico ??= Schema::hasColumn('pedido_detalles', 'retirado_en');
    }

    public function nombre(PresentacionProducto $presentacion): string
    {
        return $presentacion->producto->nombre.($presentacion->variante ? ' · '.$presentacion->variante->valor : '').' · '.$presentacion->contenido.' '.$presentacion->unidad_medida;
    }

    /** @param Collection<int, Pedido> $pedidos
     * @return array<int, int>
     */
    public function despachadas(Collection $pedidos): array
    {
        $ids = $pedidos->where('modelo_comercial', 'unidades')->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        return DB::table('pedido_despacho_lineas as l')->join('pedido_despachos as s', 's.id', '=', 'l.despacho_id')
            ->whereIn('s.pedido_id', $ids)->groupBy('l.detalle_comercial_id')->select('l.detalle_comercial_id')
            ->selectRaw('SUM(l.cantidad) cantidad')->pluck('cantidad', 'detalle_comercial_id')->map(fn ($cantidad): int => (int) $cantidad)->all();
    }

    /** La proyección no modifica las cantidades comerciales ni los movimientos.
     * @param  array<int, int>|null  $despachadas
     * @return Collection<int, PedidoDetalle>
     */
    public function lineasNormales(Pedido $pedido, ?array $despachadas = null): Collection
    {
        $pedido->loadMissing('detalles');
        $salidas = $pedido->modelo_comercial === 'unidades' ? ($despachadas ?? $this->despachadas(collect([$pedido]))) : [];

        return $pedido->detalles->filter(fn (PedidoDetalle $detalle): bool => $detalle->tipo_linea !== 'surtido')->map(function (PedidoDetalle $detalle) use ($pedido, $salidas): PedidoDetalle {
            $proyeccion = clone $detalle;
            if ($pedido->modelo_comercial === 'unidades') {
                $proyeccion->cantidad = max(0, (int) $detalle->cantidad - ($salidas[$detalle->id] ?? 0));
            } elseif ($detalle->cantidad_fisica_ajustada !== null) {
                $proyeccion->modalidad = 'unidad';
                $proyeccion->cantidad = $detalle->cantidad_fisica_ajustada;
                $proyeccion->descripcion_producto .= ' · Ajuste físico explícito';
            }

            return $proyeccion;
        })->filter(fn (PedidoDetalle $detalle): bool => $detalle->cantidad > 0)->values();
    }
}
