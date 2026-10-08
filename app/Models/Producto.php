<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(VarianteProducto::class, 'producto_id');
    }

    public function presentaciones(): HasMany
    {
        return $this->hasMany(PresentacionProducto::class, 'producto_id');
    }
}
