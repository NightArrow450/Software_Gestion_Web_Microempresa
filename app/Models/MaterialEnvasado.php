<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialEnvasado extends Model
{
    use HasFactory;

    protected $table = 'materiales_envasado';

    protected $guarded = ['id'];

    protected $casts = ['estado' => 'boolean', 'cantidad_por_unidad' => 'decimal:3'];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
