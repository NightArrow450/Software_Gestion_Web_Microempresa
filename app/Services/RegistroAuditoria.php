<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Support\Arr;

class RegistroAuditoria
{
    public static function registrar(string $accion, string $entidad, int $entidadId, array $detalle = []): void
    {
        if (! auth()->check()) {
            return;
        }
        Auditoria::create(['usuario_id' => auth()->id(), 'accion' => $accion, 'entidad' => $entidad, 'entidad_id' => $entidadId,
            'detalle' => Arr::only($detalle, ['usuario_afectado_id', 'rol_id', 'permiso_id', 'estado', 'rol_anterior_id', 'permisos_anteriores', 'permisos_nuevos', 'campos', 'movimiento_id', 'stock_anterior', 'stock_nuevo', 'motivo', 'nombre', 'cantidad', 'pedido_id', 'clasificacion', 'materiales', 'control_materiales_exacto', 'envasado_id', 'composicion_id', 'version_anterior', 'version_nueva']),
            'ip' => request()->ip(), 'created_at' => now()]);
    }
}
