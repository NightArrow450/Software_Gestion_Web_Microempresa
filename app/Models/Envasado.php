<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Envasado extends Model
{
    use HasFactory;

    protected $table = 'envasados';

    protected $guarded = ['id'];

    protected $casts = ['cantidad' => 'integer', 'unidades_revertidas' => 'integer', 'presentacion_historica' => 'array'];

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_producto_id');
    }

    public function configuracion(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionEnvasado::class, 'configuracion_envasado_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(EnvasadoConsumo::class, 'envasado_id');
    }

    public function correcciones(): HasMany
    {
        return $this->hasMany(EnvasadoCorreccion::class, 'envasado_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(EnvasadoReporte::class, 'envasado_id');
    }
}
