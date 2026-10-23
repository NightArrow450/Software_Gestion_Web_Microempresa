<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnvasadoCorreccion extends Model
{
    use HasFactory;

    protected $table = 'envasado_correcciones';

    protected $guarded = ['id'];

    protected $casts = [];

    public function materiales(): HasMany
    {
        return $this->hasMany(EnvasadoCorreccionMaterial::class, 'envasado_correccion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El historial de envasado es inmutable.'));
        static::deleting(fn () => throw new \LogicException('El historial de envasado no puede eliminarse.'));
    }
}
