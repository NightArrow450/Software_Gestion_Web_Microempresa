<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class CantidadesEnvasado
{
    public static function milesimas(string|int|float $valor): int
    {
        $texto = (string) $valor;
        if (! preg_match('/\A\d{1,10}(?:\.\d{1,3})?\z/', $texto)) {
            throw ValidationException::withMessages(['materiales' => 'Usa cantidades positivas con un máximo de tres decimales.']);
        }
        [$entero, $decimal] = array_pad(explode('.', $texto), 2, '');

        return (int) $entero * 1000 + (int) str_pad($decimal, 3, '0');
    }

    public static function decimal(int $valor): string
    {
        return intdiv($valor, 1000).'.'.str_pad((string) ($valor % 1000), 3, '0', STR_PAD_LEFT);
    }
}
