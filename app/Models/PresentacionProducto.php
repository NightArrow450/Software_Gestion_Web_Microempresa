<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresentacionProducto extends Model
{
    protected $table = 'presentaciones_producto';

    protected $fillable = [
        'producto_id',
        'variante_producto_id',
        'codigo_sku',
        'tipo_envase',
        'contenido',
        'unidad_medida',
        'tipo_empaque',
        'unidades_por_empaque',
        'venta_por_unidad',
        'venta_por_empaque',
        'precio_unitario',
        'precio_empaque',
        'estado',
    ];

    protected $casts = [
        'contenido' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'precio_empaque' => 'decimal:2',
        'venta_por_unidad' => 'boolean',
        'venta_por_empaque' => 'boolean',
        'estado' => 'boolean',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteProducto::class, 'variante_producto_id');
    }
}
