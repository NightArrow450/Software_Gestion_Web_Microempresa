<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfiguracionEnvasado extends Model
{
    use HasFactory;

    protected $table = 'configuraciones_envasado';

    protected $guarded = ['id'];

    protected $casts = ['estado' => 'boolean', 'version' => 'integer'];

    /** @return BelongsTo<PresentacionProducto, $this> */
    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_producto_id');
    }

    /** @return HasMany<MaterialEnvasado, $this> */
    public function materiales(): HasMany
    {
        return $this->hasMany(MaterialEnvasado::class, 'configuracion_envasado_id');
    }
}
