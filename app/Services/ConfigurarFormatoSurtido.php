<?php

namespace App\Services;

use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfigurarFormatoSurtido
{
    public function guardar(array $entrada, User $usuario, ?int $id = null): int
    {
        abort_unless($usuario->status && $usuario->role?->tienePermiso('surtidos.configurar'), 403);
        $datos = Validator::make($entrada, [
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'nombre' => ['required', 'string', 'max:160'],
            'unidades_por_empaque' => ['required', 'integer', 'min:2', 'max:1000'],
            'precio_empaque' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'estado' => ['required', 'boolean'],
            'version' => [$id === null ? 'nullable' : 'required', 'integer', 'min:0'],
            'skus' => ['required', 'array', 'min:1', 'max:1000'],
            'skus.*' => ['required', 'integer', 'distinct', 'exists:presentaciones_producto,id'],
        ])->validate();

        return DB::transaction(function () use ($datos, $id): int {
            $producto = Producto::whereKey($datos['producto_id'])->lockForUpdate()->firstOrFail();
            $actual = $id === null ? null : DB::table('formatos_surtidos')->where('id', $id)->lockForUpdate()->first();
            if ($id !== null && (! $actual || $actual->producto_id !== $producto->id || (int) $actual->version !== (int) $datos['version'])) {
                throw ValidationException::withMessages(['version' => 'La modalidad surtida cambió o pertenece a otro producto. Recarga la página.']);
            }
            $skus = PresentacionProducto::with(['producto', 'variante'])->whereIn('id', $datos['skus'])->orderBy('id')->lockForUpdate()->get();
            $base = $skus->firstOrFail();
            foreach ($skus as $sku) {
                if (! $sku->estado || ! $sku->producto->estado || ($sku->variante && ! $sku->variante->estado)
                    || $sku->producto_id !== $producto->id || $sku->contenido !== $base->contenido
                    || $sku->unidad_medida !== $base->unidad_medida || $sku->tipo_envase !== $base->tipo_envase) {
                    throw ValidationException::withMessages(['skus' => 'Los SKU deben estar activos y corresponder al mismo producto, capacidad, unidad y tipo de envase.']);
                }
            }
            if ($actual && ((float) $actual->contenido !== (float) $base->contenido || $actual->unidad_medida !== $base->unidad_medida || $actual->tipo_envase !== ($base->tipo_envase ?? ''))) {
                throw ValidationException::withMessages(['skus' => 'La presentación física del formato no puede cambiar. Crea otra habilitación comercial.']);
            }
            $fila = ['producto_id' => $producto->id, 'nombre' => trim($datos['nombre']), 'contenido' => $base->contenido,
                'unidad_medida' => $base->unidad_medida, 'tipo_envase' => $base->tipo_envase ?? '',
                'unidades_por_empaque' => $datos['unidades_por_empaque'], 'precio_empaque' => $datos['precio_empaque'],
                'estado' => $datos['estado'], 'version' => $actual ? $actual->version + 1 : 0, 'updated_at' => now()];
            if ($actual) {
                DB::table('formatos_surtidos')->where('id', $id)->update($fila);
                DB::table('formato_surtido_skus')->where('formato_surtido_id', $id)->delete();
            } else {
                $id = DB::table('formatos_surtidos')->insertGetId($fila + ['created_at' => now()]);
            }
            foreach ($skus as $sku) {
                DB::table('formato_surtido_skus')->insert(['formato_surtido_id' => $id, 'presentacion_producto_id' => $sku->id]);
            }
            RegistroAuditoria::registrar('surtido_configurado', 'formato_surtido', $id, ['estado' => $fila['estado'], 'campos' => ['habilitación', 'SKU compatibles', 'precio comercial', 'tamaño']]);

            return $id;
        }, 3);
    }
}
