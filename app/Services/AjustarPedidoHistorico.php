<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AjustarPedidoHistorico
{
    public function guardar(Pedido $referencia, array $entrada, User $usuario): Pedido
    {
        abort_unless($usuario->status && $usuario->role?->tienePermiso('pedidos.editar'), 403);
        $datos = Validator::make($entrada, [
            'motivo' => ['required', 'string', 'min:10', 'max:1000'], '_version' => ['required', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1', 'max:18'],
            'detalles.*.id' => ['nullable', 'integer'], 'detalles.*.presentacion_producto_id' => ['nullable', 'integer'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'detalles.*.modalidad' => ['required', 'in:unidad,empaque'], 'ajustes_historicos' => ['sometimes', 'array'],
            'detalles.*.unidades_por_empaque' => ['exclude_unless:detalles.*.modalidad,empaque', 'nullable', 'integer', 'min:2', 'max:1000'],
            'ajustes_historicos.*.importe_revisado' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'ajustes_historicos.*.cantidad_fisica_revisada' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
        ])->validate();

        return DB::transaction(function () use ($referencia, $entrada, $datos, $usuario): Pedido {
            $pedido = Pedido::whereKey($referencia->id)->lockForUpdate()->firstOrFail();
            if ($pedido->modelo_comercial !== null || (int) $pedido->version !== (int) $datos['_version']) {
                throw ValidationException::withMessages(['edicion' => 'El pedido cambió o no pertenece al modelo histórico. Recarga la pantalla.']);
            }
            $anteriores = $pedido->detalles()->lockForUpdate()->get()->keyBy('id');
            $totalOriginalCentimos = (int) round((float) $pedido->total * 100);
            $lineasOriginalesCentimos = (int) $anteriores->sum(fn ($d): int => (int) round((float) $d->importe * 100));
            $fisico = $pedido->despachado_en !== null || DB::table('movimientos_inventario')->where('pedido_id', $pedido->id)->exists()
                || in_array($pedido->estado, ControlEntregaPedido::ESTADOS, true) || $pedido->estado === 'cancelado';
            $numero = (int) DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->max('numero');
            if (! DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->exists()) {
                $this->fotografiar($pedido, $usuario, 0, 'Fotografía original anterior al primer ajuste histórico.');
            }
            $vistos = [];
            $total = 0;
            $cambio = false;
            foreach ($datos['detalles'] as $i => $fila) {
                $id = (int) ($fila['id'] ?? 0);
                $actual = $id ? $anteriores->get($id) : $anteriores->first(fn ($d): bool => ! isset($vistos[$d->id])
                    && (int) $d->presentacion_producto_id === (int) ($fila['presentacion_producto_id'] ?? 0)
                    && $d->modalidad === $fila['modalidad'] && $d->tipo_linea === ($entrada['detalles'][$i]['tipo_linea'] ?? 'normal')
                    && ($d->modalidad !== 'empaque' || ! isset($entrada['detalles'][$i]['unidades_por_empaque']) || (int) $d->unidadesPorEmpaqueEfectivas() === (int) $entrada['detalles'][$i]['unidades_por_empaque']));
                if (($id && ! $actual) || ($actual && isset($vistos[$actual->id]))) {
                    throw ValidationException::withMessages(['detalles' => 'La línea está repetida o no pertenece al pedido histórico.']);
                }
                $cantidad = (int) $fila['cantidad'];
                if ($actual) {
                    $vistos[$actual->id] = true;
                    $cantidadAnterior = (int) $actual->cantidad;
                    if ($actual->modalidad !== $fila['modalidad'] || (int) $actual->presentacion_producto_id !== (int) ($fila['presentacion_producto_id'] ?? 0)
                        || ($actual->modalidad === 'empaque' && isset($entrada['detalles'][$i]['unidades_por_empaque']) && (int) $entrada['detalles'][$i]['unidades_por_empaque'] !== (int) $actual->unidadesPorEmpaqueEfectivas())) {
                        throw ValidationException::withMessages(['detalles' => 'La modalidad, el SKU y el tamaño históricos se conservan. Retira la línea pendiente y añade otra, o registra un ajuste físico e importe revisado.']);
                    }
                    $ajuste = $datos['ajustes_historicos'][$actual->id] ?? [];
                    $importeRevisado = $ajuste['importe_revisado'] ?? null;
                    $unidadesRevisadas = $ajuste['cantidad_fisica_revisada'] ?? null;
                    if ($fisico && ($cantidad !== $cantidadAnterior || $unidadesRevisadas !== null)) {
                        throw ValidationException::withMessages(['detalles' => 'Existen hechos físicos: registra una incidencia o retorno sin cambiar las cantidades que salieron.']);
                    }
                    if (($unidadesRevisadas !== null && ($actual->tipo_linea === 'surtido' || $importeRevisado === null))
                        || ($cantidad !== $cantidadAnterior && $actual->cantidad_fisica_ajustada !== null && $importeRevisado === null)) {
                        throw ValidationException::withMessages(['ajustes_historicos' => 'El ajuste físico requiere importe revisado explícito; los surtidos conservan sus componentes individuales.']);
                    }
                    if ($cantidad > 0 && $cantidad !== $cantidadAnterior && $importeRevisado === null
                        && app(CalculadoraPedido::class)->calcularImporte($cantidadAnterior, $actual->precio_unitario) !== $actual->importe) {
                        throw ValidationException::withMessages(['ajustes_historicos' => 'Esta línea tiene un importe histórico especial. Indica el importe revisado; no se deduce una nueva tarifa.']);
                    }
                    if ($importeRevisado !== null) {
                        abort_unless(in_array($usuario->role?->nombre, ['Administrador', 'Operaciones Comerciales'], true), 403);
                    }
                    $importe = $cantidad === $cantidadAnterior ? $actual->importe
                        : ($cantidad === 0 ? '0.00' : app(CalculadoraPedido::class)->calcularImporte($cantidad, $actual->precio_unitario));
                    if ($importeRevisado !== null) {
                        $importe = number_format((float) $importeRevisado, 2, '.', '');
                    }
                    if ((float) $importe > 999999.99) {
                        throw ValidationException::withMessages(['detalles.'.$i.'.cantidad' => 'El importe supera S/ 999 999,99.']);
                    }
                    $cambio = $cambio || $cantidad !== $cantidadAnterior || (string) $importe !== (string) $actual->importe || $unidadesRevisadas !== null;
                    $actual->update(['cantidad' => $cantidad, 'importe' => $importe, 'cantidad_fisica_ajustada' => $unidadesRevisadas ?? $actual->cantidad_fisica_ajustada,
                        'retirado_en' => $cantidad === 0 ? now() : null]);
                    if ($actual->tipo_linea === 'surtido') {
                        $paquetes = app(GestionarSurtidos::class);
                        $enviados = $entrada['detalles'][$i]['paquetes_json'] ?? null;
                        if ($cantidad === 0) {
                            DB::table('pedido_paquetes_surtidos')->where('pedido_detalle_id', $actual->id)->update(['activo' => false]);
                        } elseif ($enviados !== null) {
                            $normalizar = static fn (array $lista): array => array_map(static fn (array $p): array => ['estado' => $p['estado'], 'componentes' => collect($p['componentes'])->map(fn ($c): array => ['presentacion_producto_id' => (int) $c['presentacion_producto_id'], 'cantidad' => (int) $c['cantidad']])->sortBy('presentacion_producto_id')->values()->all()], $lista);
                            if ($normalizar($paquetes->leerPaquetes($enviados)) !== $normalizar($paquetes->paquetes($actual)) || $cantidad !== $cantidadAnterior) {
                                if ($fisico) {
                                    throw ValidationException::withMessages(['detalles' => 'Las composiciones despachadas se rectifican mediante incidencias, no en la edición comercial.']);
                                }
                                if ($pedido->estado === 'listo_despacho') {
                                    $pedido->update(['estado' => 'en_preparacion']);
                                }
                                $paquetes->guardar($actual, $paquetes->leerPaquetes($enviados), $usuario, $datos['motivo']);
                                $cambio = true;
                            }
                        }
                    }
                } else {
                    if ($fisico || $fila['modalidad'] !== 'unidad' || $cantidad < 1 || ($entrada['detalles'][$i]['tipo_linea'] ?? 'normal') !== 'normal') {
                        throw ValidationException::withMessages(['detalles' => 'Solo se añaden SKU por unidades a históricos pendientes; no se crean nuevas líneas comerciales por empaque.']);
                    }
                    $sku = PresentacionProducto::with(['producto', 'variante'])->find($fila['presentacion_producto_id']);
                    if (! $sku || ! $sku->estado || ! $sku->producto->estado || ($sku->variante && ! $sku->variante->estado) || (float) $sku->precio_unitario <= 0) {
                        throw ValidationException::withMessages(['detalles' => 'Selecciona un SKU activo con precio unitario.']);
                    }
                    $importe = app(CalculadoraPedido::class)->calcularImporte($cantidad, $sku->precio_unitario);
                    if ((float) $importe > 999999.99) {
                        throw ValidationException::withMessages(['detalles' => 'El importe supera S/ 999 999,99.']);
                    }
                    $pedido->detalles()->create(['presentacion_producto_id' => $sku->id, 'tipo_linea' => 'normal', 'modalidad' => 'unidad', 'cantidad' => $cantidad,
                        'codigo_sku' => $sku->codigo_sku, 'descripcion_producto' => app(GestionarSurtidos::class)->nombre($sku), 'precio_unitario' => $sku->precio_unitario, 'importe' => $importe]);
                    $cambio = true;
                }
                if ($cantidad > 0) {
                    $total += (int) round((float) $importe * 100);
                }
            }
            foreach ($anteriores as $id => $anterior) {
                if (! isset($vistos[$id])) {
                    if ($fisico) {
                        throw ValidationException::withMessages(['detalles' => 'No se retiran líneas con hechos físicos registrados.']);
                    }
                    $anterior->update(['retirado_en' => now()]);
                    DB::table('pedido_paquetes_surtidos')->where('pedido_detalle_id', $id)->update(['activo' => false]);
                    $cambio = true;
                }
            }
            $totalRevisado = $totalOriginalCentimos + $total - $lineasOriginalesCentimos;
            if ($totalRevisado < 0) {
                throw ValidationException::withMessages(['ajustes_historicos' => 'El ajuste dejaría un total negativo. Revisa la diferencia económica histórica.']);
            }
            $pedido->update(['observaciones' => $datos['observaciones'] ?? $pedido->observaciones, 'total' => number_format($totalRevisado / 100, 2, '.', '')]);
            if ($cambio && $pedido->estado === 'listo_despacho' && ! $fisico) {
                $pedido->update(['estado' => 'en_preparacion']);
            }
            $this->fotografiar($pedido, $usuario, $numero + 1, $datos['motivo']);
            RegistroAuditoria::registrar('pedido_historico_ajustado', 'pedido', $pedido->id, ['version_anterior' => $numero, 'version_nueva' => $numero + 1, 'motivo' => $datos['motivo'], 'campos' => ['cantidades', 'importe revisado', 'sin conversión de precios', 'hechos físicos conservados']]);

            return $pedido->fresh();
        }, 3);
    }

    private function fotografiar(Pedido $pedido, User $usuario, int $numero, string $motivo): void
    {
        $lineas = $pedido->detalles()->get()->map(fn ($d): array => $d->getAttributes() + ['paquetes' => $d->tipo_linea === 'surtido' ? app(GestionarSurtidos::class)->paquetes($d) : []])->all();
        DB::table('pedido_versiones')->insert(['pedido_id' => $pedido->id, 'usuario_id' => $usuario->id, 'numero' => $numero, 'motivo' => $motivo,
            'datos' => json_encode(['modelo_comercial' => 'historico', 'cabecera' => $pedido->getAttributes(), 'lineas' => $lineas, 'agrupaciones' => []], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
