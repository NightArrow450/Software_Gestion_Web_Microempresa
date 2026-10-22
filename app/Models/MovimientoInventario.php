<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'presentacion_producto_id', 'insumo_id', 'pedido_id', 'usuario_id',
        'origen_tipo', 'origen_id', 'tipo', 'cantidad', 'stock_anterior', 'stock_nuevo', 'motivo',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'stock_anterior' => 'decimal:3',
        'stock_nuevo' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Los movimientos de inventario son inmutables. Registra un movimiento de ajuste.'));
        static::deleting(fn () => throw new \LogicException('El historial de inventario no puede eliminarse.'));
        static::creating(function (self $movimiento): void {
            if ($movimiento->origen_tipo === null && $movimiento->origen_id === null) {
                $movimiento->origen_tipo = $movimiento->pedido_id !== null ? 'despacho' : 'manual';
                $movimiento->origen_id = $movimiento->pedido_id !== null
                    ? (string) $movimiento->pedido_id : (string) Str::ulid();
            }
        });
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_producto_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}
