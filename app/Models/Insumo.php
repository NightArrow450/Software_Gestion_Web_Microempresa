<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insumo extends Model
{
    protected $table = 'insumos';

    protected $fillable = [
        'codigo', 'nombre', 'categoria', 'unidad_medida',
        'cantidad_actual', 'stock_minimo', 'estado',
    ];

    protected $casts = [
        'cantidad_actual' => 'decimal:3',
        'stock_minimo' => 'decimal:3',
        'estado' => 'boolean',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'insumo_id');
    }
}
