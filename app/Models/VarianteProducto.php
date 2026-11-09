<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VarianteProducto extends Model
{
    protected $table = 'variantes_producto';

    protected $fillable = [
        'producto_id',
        'tipo',
        'valor',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** @return HasMany<PresentacionProducto, $this> */
    public function presentaciones(): HasMany
    {
        return $this->hasMany(PresentacionProducto::class, 'variante_producto_id');
    }
}
