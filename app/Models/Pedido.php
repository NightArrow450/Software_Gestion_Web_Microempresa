<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $version */
class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'cliente_id', 'usuario_id', 'fecha_pedido', 'fecha_entrega',
        'condicion_pago', 'estado', 'total', 'observaciones',
        'cliente_nombre', 'cliente_documento', 'cliente_telefono',
        'cliente_mercado', 'cliente_puesto_referencia',
        'cliente_distrito', 'cliente_direccion', 'procesado_por_id', 'procesado_en',
    ];

    protected $casts = [
        'fecha_pedido' => 'date', 'fecha_entrega' => 'date',
        'procesado_en' => 'datetime', 'total' => 'decimal:2',
    ];

    public function getNumeroAttribute(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return BelongsTo<User, $this> */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function procesadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'procesado_por_id');
    }

    /** @return HasMany<PedidoDetalle, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class, 'pedido_id');
    }

    /** @return HasMany<PedidoEstadoHistorial, $this> */
    public function historialEstados(): HasMany
    {
        return $this->hasMany(PedidoEstadoHistorial::class, 'pedido_id');
    }
}
