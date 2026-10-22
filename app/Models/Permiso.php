<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permiso extends Model
{
    use SoftDeletes;

    protected $table = 'permisos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_sistema',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'rol_permiso',
            'permiso_id',
            'rol_id'
        )->withTimestamps();
    }
}
