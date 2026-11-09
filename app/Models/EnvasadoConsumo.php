<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvasadoConsumo extends Model
{
    use HasFactory;

    protected $table = 'envasado_consumos';

    protected $guarded = ['id'];

    protected $casts = ['cantidad_por_unidad' => 'decimal:3', 'cantidad' => 'decimal:3'];

    /** @return BelongsTo<Insumo, $this> */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El historial de envasado es inmutable.'));
        static::deleting(fn () => throw new \LogicException('El historial de envasado no puede eliminarse.'));
    }
}
