<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

class LeerAgrupaciones
{
    public static function leer(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [];
        }
        if (is_string($valor)) {
            try {
                $valor = json_decode($valor, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new InvalidArgumentException('La agrupación enviada no es un JSON válido.', previous: $error);
            }
        }
        if (! is_array($valor) || ! array_is_list($valor) || count($valor) > 1000) {
            throw new InvalidArgumentException('Envía una lista válida de hasta 1000 agrupaciones opcionales.');
        }

        return $valor;
    }
}
