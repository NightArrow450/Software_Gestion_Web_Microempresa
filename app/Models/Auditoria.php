<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['detalle' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La auditoría es inmutable.'));
        static::deleting(fn () => throw new \LogicException('La auditoría no se puede eliminar.'));
    }
}
