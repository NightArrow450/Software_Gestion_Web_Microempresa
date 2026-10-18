<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    protected $table = 'inventarios';
    protected $fillable = ['presentacion_producto_id', 'cantidad_actual', 'stock_minimo'];
    protected $casts = ['cantidad_actual' => 'integer', 'stock_minimo' => 'integer'];

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_producto_id');
    }
}
