<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoEstadoHistorial extends Model
{
    public $timestamps = false;

    protected $table = 'pedido_estados_historial';

    protected $fillable = [
        'usuario_id',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
