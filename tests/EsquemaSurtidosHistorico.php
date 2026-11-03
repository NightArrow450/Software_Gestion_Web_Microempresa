<?php

namespace Tests;

class EsquemaSurtidosHistorico
{
    public const CIERRE = '2026_11_03_031045_cerrar_esquema_surtidos_obsoletos.php';

    /** @return list<string> */
    public static function rutas(): array
    {
        return array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $ruta): bool => basename($ruta) < self::CIERRE));
    }
}
