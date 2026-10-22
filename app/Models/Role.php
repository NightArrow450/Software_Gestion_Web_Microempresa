<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'es_sistema',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(
            User::class,
            'role_id'
        );
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(
            Permiso::class,
            'rol_permiso',
            'rol_id',
            'permiso_id'
        )->withTimestamps();
    }

    public function tienePermiso(string $permiso): bool
    {
        return $this->permisos()
            ->where('permisos.nombre', $permiso)
            ->exists();
    }
}
