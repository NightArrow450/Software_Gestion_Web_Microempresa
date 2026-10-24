<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'es_sistema',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(
            User::class,
            'role_id'
        );
    }

    /** @return BelongsToMany<Permiso, $this> */
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
        if ($this->relationLoaded('permisos')) {
            return $this->permisos->contains('nombre', $permiso);
        }

        return $this->permisos()
            ->where('permisos.nombre', $permiso)
            ->exists();
    }
}
