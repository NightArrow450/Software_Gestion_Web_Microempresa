<?php

namespace App;

enum EstadoPedido: string
{
    case Registrado = 'registrado';
    case Observado = 'observado';
    case Confirmado = 'confirmado';
    case EnPreparacion = 'en_preparacion';
    case ListoDespacho = 'listo_despacho';
    case Despachado = 'despachado';
    case EntregaPendiente = 'entrega_pendiente';
    case EntregaParcial = 'entrega_parcial';
    case Rechazado = 'rechazado';
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';

    public function permite(self $destino): bool
    {
        if ($this === self::Entregado && $destino === self::Entregado) {
            return false;
        }

        return in_array($destino, match ($this) {
            self::Registrado => [self::Confirmado, self::Observado, self::Cancelado],
            self::Observado => [self::Confirmado, self::Cancelado],
            self::Confirmado => [self::EnPreparacion, self::Cancelado],
            self::EnPreparacion => [self::ListoDespacho],
            self::ListoDespacho => [self::Despachado],
            self::Despachado, self::EntregaPendiente, self::EntregaParcial, self::Rechazado, self::Entregado => [self::EntregaPendiente, self::EntregaParcial, self::Rechazado, self::Entregado],
            default => [],
        }, true);
    }
}
