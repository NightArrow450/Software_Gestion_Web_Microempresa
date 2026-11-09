<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $version */
class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'codigo',
        'categoria_id',
        'nombre',
        'marca',
        'descripcion',
        'imagen_referencia',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /** @return HasMany<VarianteProducto, $this> */
    public function variantes(): HasMany
    {
        return $this->hasMany(VarianteProducto::class, 'producto_id');
    }

    /** @return HasMany<PresentacionProducto, $this> */
    public function presentaciones(): HasMany
    {
        return $this->hasMany(PresentacionProducto::class, 'producto_id');
    }
}
