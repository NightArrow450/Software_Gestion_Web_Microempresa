<?php

namespace App\Services;

use InvalidArgumentException;

class CalculadoraPedido
{
    /** Operamos en céntimos para evitar errores acumulados de coma flotante. */
    public function calcularImporte(int $cantidad, string|float $precio): string
    {
        if ($cantidad < 1 || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string) $precio)) {
            throw new InvalidArgumentException('Cantidad o precio inválido.');
        }

        $centimos = (int) round((float) $precio * 100);
        return number_format(($centimos * $cantidad) / 100, 2, '.', '');
    }
}
