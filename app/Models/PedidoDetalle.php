<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoDetalle extends Model
{
    protected $table = 'pedido_detalles';

    protected $fillable = [
        'presentacion_producto_id',
        'codigo_sku',
        'descripcion_producto',
        'modalidad',
        'unidades_por_empaque',
        'cantidad',
        'precio_unitario',
        'importe',
    ];

    protected $casts = [
        'unidades_por_empaque' => 'integer',
        'precio_unitario' => 'decimal:2',
        'importe' => 'decimal:2',
    ];

    /** @return BelongsTo<Pedido, $this> */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function unidadesPorEmpaqueEfectivas(?PresentacionProducto $presentacion = null): ?int
    {
        if ($this->modalidad !== 'empaque') {
            return null;
        }

        $unidades = $this->unidades_por_empaque ?? ($presentacion ?? $this->presentacion)?->unidades_por_empaque;

        return $unidades === null ? null : (int) $unidades;
    }

    public function descripcionModalidad(): string
    {
        if ($this->modalidad !== 'empaque') {
            return ucfirst($this->modalidad);
        }

        $unidades = $this->unidadesPorEmpaqueEfectivas();

        return $unidades > 0 ? 'Empaque de '.$unidades : 'Empaque (tamaño no disponible)';
    }

    /** @return BelongsTo<PresentacionProducto, $this> */
    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(
            PresentacionProducto::class,
            'presentacion_producto_id'
        );
    }
}
