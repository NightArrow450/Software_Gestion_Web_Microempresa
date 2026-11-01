<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class BusquedaCatalogo
{
    private const ACENTOS = ['Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'];

    /** Las columnas son identificadores internos, nunca valores recibidos del formulario. */
    public static function coincidencia(Builder $consulta, array $columnas, string $termino): void
    {
        $normalizado = mb_strtolower(strtr(trim($termino), self::ACENTOS), 'UTF-8');
        if ($normalizado === '') {
            return;
        }
        $consulta->where(function (Builder $grupo) use ($columnas, $normalizado): void {
            foreach ($columnas as $columna) {
                if (! preg_match('/\A[a-z_]+(?:\.[a-z_]+)?\z/', $columna)) {
                    throw new \InvalidArgumentException('Columna de búsqueda no autorizada.');
                }
                $sql = 'COALESCE('.$columna.", '')";
                foreach (self::ACENTOS as $acento => $letra) {
                    $sql = "REPLACE($sql, '$acento', '$letra')";
                }
                $grupo->orWhereRaw('INSTR(LOWER('.$sql.'), ?) > 0', [$normalizado]);
            }
        });
    }

    public static function vendibles(Builder $consulta): void
    {
        $consulta->where('estado', true)->where('precio_unitario', '>', 0)
            ->whereHas('producto', fn (Builder $q) => $q->where('estado', true))
            ->where(fn (Builder $q) => $q->whereNull('variante_producto_id')->orWhereHas('variante', fn (Builder $v) => $v->where('estado', true)));
    }
}
