<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnvasadoCorreccionMaterial extends Model
{
    use HasFactory;

    protected $table = 'envasado_correccion_materiales';

    protected $guarded = ['id'];

    protected $casts = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El historial de envasado es inmutable.'));
        static::deleting(fn () => throw new \LogicException('El historial de envasado no puede eliminarse.'));
    }
}
