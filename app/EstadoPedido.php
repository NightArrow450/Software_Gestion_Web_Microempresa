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
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';

    public function permite(self $destino): bool
    {
        return in_array($destino, match ($this) {
            self::Registrado => [self::Confirmado, self::Observado, self::Cancelado],
            self::Observado => [self::Confirmado, self::Cancelado],
            self::Confirmado => [self::EnPreparacion, self::Cancelado],
            self::EnPreparacion => [self::ListoDespacho],
            self::ListoDespacho => [self::Despachado],
            self::Despachado => [self::Entregado],
            default => [],
        }, true);
    }
}
