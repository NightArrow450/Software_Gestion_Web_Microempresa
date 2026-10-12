<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresentacionProducto extends Model
{
    protected $table = 'presentaciones_producto';

    protected $fillable = [
        'producto_id',
        'variante_producto_id',
        'codigo_sku',
        'tipo_envase',
        'contenido',
        'unidad_medida',
        'tipo_empaque',
        'unidades_por_empaque',
        'venta_por_unidad',
        'venta_por_empaque',
        'precio_unitario',
        'precio_empaque',
        'estado',
    ];

    protected $casts = [
        'contenido' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'precio_empaque' => 'decimal:2',
        'venta_por_unidad' => 'boolean',
        'venta_por_empaque' => 'boolean',
        'estado' => 'boolean',
    ];

    /**
     * Precio de caja/paquete a partir del precio de una unidad y la cantidad.
     * Cálculo en céntimos para evitar errores al multiplicar importes.
     */
    public static function calcularPrecioEmpaque(string|int|float|null $precioUnitario, ?int $unidades): ?string
    {
        if ($precioUnitario === null || $precioUnitario === '' || $unidades === null || $unidades < 1) {
            return null;
        }

        $centimosUnitarios = (int) round((float) $precioUnitario * 100);

        return number_format(($centimosUnitarios * $unidades) / 100, 2, '.', '');
    }

    /**
     * Las pantallas y el Sprint 2 usan el precio calculado aun para productos
     * guardados antes de este cambio. Si no hay precio base, conserva el
     * precio histórico hasta que se edite la presentación.
     */
    public function getPrecioEmpaqueAttribute($valor): ?string
    {
        if ($this->venta_por_empaque) {
            $precio = self::calcularPrecioEmpaque(
                $this->precio_unitario,
                $this->unidades_por_empaque === null ? null : (int) $this->unidades_por_empaque
            );

            if ($precio !== null) {
                return $precio;
            }
        }

        return $valor === null ? null : number_format((float) $valor, 2, '.', '');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteProducto::class, 'variante_producto_id');
    }
}
