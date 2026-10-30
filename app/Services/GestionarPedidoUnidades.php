<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GestionarPedidoUnidades
{
    public function guardar(array $entrada, User $usuario, ?Pedido $referencia = null): Pedido
    {
        abort_unless($usuario->status && $usuario->role?->tienePermiso($referencia ? 'pedidos.editar' : 'pedidos.crear'), 403);
        $hoy = CarbonImmutable::today('America/Lima');
        $datos = Validator::make($entrada, [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'fecha_pedido' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$hoy->toDateString(), ...($referencia ? [] : ['after_or_equal:'.$hoy->subDays(30)->toDateString()])],
            'fecha_entrega' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_pedido'],
            'condicion_pago' => ['required', Rule::in(['contado', 'credito'])],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'motivo' => [$referencia ? 'required' : 'nullable', 'string', 'min:10', 'max:1000'],
            '_version' => [$referencia ? 'required' : 'nullable', 'integer', 'min:0'],
            'detalles' => ['required', 'array', 'min:1', 'max:18'],
            'detalles.*.id' => ['nullable', 'integer', 'min:1'],
            'detalles.*.presentacion_producto_id' => ['required', 'integer', 'distinct'],
            'detalles.*.cantidad' => ['required', 'integer', $referencia ? 'min:0' : 'min:1', 'max:4294967295'],
            'detalles.*.cantidad_espera' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'detalles.*.modalidad' => ['sometimes', 'in:unidad'],
            'detalles.*.tipo_linea' => ['sometimes', 'in:normal'],
            'agrupaciones' => ['sometimes', 'array', 'list', 'max:1000'],
            'agrupaciones.*.capacidad' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'agrupaciones.*.identidad' => ['nullable', 'uuid', 'distinct'],
            'agrupaciones.*.componentes' => ['required', 'array', 'list', 'min:1', 'max:18'],
            'agrupaciones.*.componentes.*.sku_id' => ['required', 'integer', 'min:1'],
            'agrupaciones.*.componentes.*.cantidad' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ], ['detalles.*.modalidad.in' => 'Los pedidos nuevos se venden únicamente por unidades físicas.', 'detalles.*.presentacion_producto_id.distinct' => 'Agrupa la cantidad del mismo SKU en una sola línea comercial.'])->validate();
        if (collect($datos['detalles'])->contains(fn (array $fila): bool => (int) ($fila['cantidad_espera'] ?? 0) > 0) && empty($datos['motivo'])) {
            throw ValidationException::withMessages(['motivo' => 'Registra el motivo de la decisión de dejar unidades en espera; no implica una aceptación automática del cliente.']);
        }
        if (CarbonImmutable::parse($datos['fecha_entrega'])->greaterThan(CarbonImmutable::parse($datos['fecha_pedido'])->addDays(90))) {
            throw ValidationException::withMessages(['fecha_entrega' => 'La entrega no puede superar los 90 días desde la fecha del pedido.']);
        }

        return DB::transaction(function () use ($datos, $usuario, $referencia): Pedido {
            $pedido = $referencia ? Pedido::whereKey($referencia->id)->lockForUpdate()->firstOrFail() : null;
            if ($pedido && ($pedido->modelo_comercial !== 'unidades' || (int) $pedido->version !== (int) $datos['_version'])) {
                throw ValidationException::withMessages(['version' => 'El pedido es histórico o cambió. No se convierte su modalidad ni se sobrescribe una versión anterior.']);
            }
            $cliente = Cliente::whereKey($datos['cliente_id'])->first();
            if (! $cliente || (! $cliente->estado && (! $pedido || $pedido->cliente_id !== $cliente->id))) {
                throw ValidationException::withMessages(['cliente_id' => 'Selecciona un cliente activo.']);
            }
            $anteriores = $pedido ? $pedido->detalles()->lockForUpdate()->get()->keyBy('id') : collect();
            $skus = PresentacionProducto::with(['producto', 'variante'])->whereIn('id', array_column($datos['detalles'], 'presentacion_producto_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lineas = [];
            $total = 0;
            $ids = [];
            foreach ($datos['detalles'] as $indice => $fila) {
                $id = (int) ($fila['id'] ?? 0);
                $anterior = $id ? $anteriores->get($id) : null;
                if ($id && (! $anterior || isset($ids[$id]))) {
                    throw ValidationException::withMessages(['detalles' => 'Una línea está repetida o no pertenece al pedido.']);
                }
                $ids[$id] = $id > 0;
                $sku = $skus->get((int) $fila['presentacion_producto_id']);
                $cantidad = (int) $fila['cantidad'];
                if (! $sku || (($anterior === null || $cantidad > (int) $anterior->cantidad) && $cantidad > 0 && (! $sku->estado || ! $sku->producto->estado || ($sku->variante && ! $sku->variante->estado)))) {
                    throw ValidationException::withMessages(['detalles.'.$indice.'.presentacion_producto_id' => 'Selecciona un SKU activo y compatible.']);
                }
                $misma = $anterior && (int) $anterior->presentacion_producto_id === (int) $sku->id;
                if ($anterior && ! $misma) {
                    throw ValidationException::withMessages(['detalles' => 'Para sustituir un SKU, retira su línea y agrega la nueva. El motivo y ambas cantidades se conservan en la versión.']);
                }
                $precio = $misma ? $anterior->precio_unitario : $sku->precio_unitario;
                if ($cantidad > 0 && ($precio === null || (float) $precio <= 0)) {
                    throw ValidationException::withMessages(['detalles.'.$indice.'.presentacion_producto_id' => 'Falta el precio unitario del SKU. Operaciones debe registrarlo en el catálogo; no se deduce de un empaque.']);
                }
                $importe = $cantidad === 0 ? '0.00' : app(CalculadoraPedido::class)->calcularImporte($cantidad, (string) $precio);
                if ((float) $importe > 999999.99) {
                    throw ValidationException::withMessages(['detalles.'.$indice.'.cantidad' => 'El importe de la línea supera S/ 999 999,99.']);
                }
                $espera = (int) ($fila['cantidad_espera'] ?? 0);
                if ($espera > $cantidad) {
                    throw ValidationException::withMessages(['detalles' => 'Las unidades en espera no pueden superar la cantidad vigente.']);
                }
                $total += (int) round((float) $importe * 100);
                $lineas[] = ['id' => $id, 'presentacion_producto_id' => $sku->id, 'codigo_sku' => $misma ? $anterior->codigo_sku : $sku->codigo_sku,
                    'descripcion_producto' => $misma ? $anterior->descripcion_producto : mb_substr($sku->producto->nombre.($sku->variante ? ' · '.$sku->variante->valor : '').' · '.$sku->contenido.' '.$sku->unidad_medida, 0, 255),
                    'tipo_linea' => 'normal', 'modalidad' => 'unidad', 'unidades_por_empaque' => null, 'cantidad' => $cantidad, 'cantidad_espera' => $espera,
                    'precio_unitario' => $precio, 'importe' => $importe];
            }
            $despachadas = $pedido ? app(AtenderPedidoUnidades::class)->despachadas($pedido->id) : collect();
            if ($pedido && $despachadas->isNotEmpty() && ($pedido->cliente_id !== $cliente->id || $pedido->fecha_pedido->toDateString() !== $datos['fecha_pedido'])) {
                throw ValidationException::withMessages(['cliente_id' => 'La identidad comercial del despacho se conserva. Registra una incidencia para rectificarla sin reescribir los hechos.']);
            }
            foreach ($anteriores as $id => $anterior) {
                $nueva = collect($lineas)->firstWhere('id', $id);
                $cantidad = $nueva['cantidad'] ?? 0;
                if ($cantidad < (int) $despachadas->get($id, 0)) {
                    throw ValidationException::withMessages(['detalles' => 'No se retiran unidades ya despachadas. Registra una incidencia o un retorno sin alterar su salida física.']);
                }
                if ($pedido && in_array($pedido->estado, ['entregado', 'rechazado', 'cancelado'], true) && ($cantidad !== (int) $anterior->cantidad || $nueva === null)) {
                    throw ValidationException::withMessages(['detalles' => 'La atención cerrada conserva sus cantidades. Utiliza las rectificaciones autorizadas e incidencias.']);
                }
            }
            if ($pedido && in_array($pedido->estado, ['entregado', 'rechazado', 'cancelado'], true) && collect($lineas)->contains('id', 0)) {
                throw ValidationException::withMessages(['detalles' => 'No se agregan salidas comerciales a una atención cerrada. Registra la incidencia y, si corresponde, otro pedido.']);
            }
            foreach ($lineas as $linea) {
                if ((int) $linea['cantidad_espera'] > (int) $linea['cantidad'] - (int) $despachadas->get($linea['id'], 0)) {
                    throw ValidationException::withMessages(['detalles' => 'Las unidades ya despachadas no pueden volver a quedar en espera.']);
                }
            }
            $cabecera = ['cliente_id' => $cliente->id, 'fecha_pedido' => $datos['fecha_pedido'], 'fecha_entrega' => $datos['fecha_entrega'], 'condicion_pago' => $datos['condicion_pago'],
                'observaciones' => $datos['observaciones'] ?? null, 'total' => number_format($total / 100, 2, '.', ''), 'cliente_nombre' => $cliente->nombre,
                'cliente_documento' => $cliente->documento, 'cliente_telefono' => $cliente->telefono, 'cliente_mercado' => $cliente->mercado,
                'cliente_puesto_referencia' => $cliente->puesto_referencia, 'cliente_distrito' => $cliente->distrito, 'cliente_direccion' => $cliente->direccion];
            if ($pedido && $pedido->cliente_id === $cliente->id) {
                foreach (['cliente_nombre', 'cliente_documento', 'cliente_telefono', 'cliente_mercado', 'cliente_puesto_referencia', 'cliente_distrito', 'cliente_direccion'] as $campoCliente) {
                    $cabecera[$campoCliente] = $pedido->{$campoCliente};
                }
            }
            if ($pedido) {
                if ($pedido->estado === 'listo_despacho') {
                    $cabecera['estado'] = 'en_preparacion';
                }
                $pedido->setAttribute('version', DB::raw('version + 1'));
                $pedido->update($cabecera);
            } else {
                $pedido = Pedido::create($cabecera + ['usuario_id' => $usuario->id, 'estado' => 'registrado', 'modelo_comercial' => 'unidades']);
                $pedido->historialEstados()->create(['usuario_id' => $usuario->id, 'estado_nuevo' => 'registrado', 'motivo' => 'Venta por unidades físicas registrada.']);
            }
            $vigentes = [];
            foreach ($lineas as $datosLinea) {
                $id = $datosLinea['id'];
                unset($datosLinea['id']);
                if ($id && $datosLinea['cantidad'] > 0) {
                    $detalle = $anteriores->get($id);
                    $detalle->update($datosLinea);
                } elseif ($datosLinea['cantidad'] > 0) {
                    $detalle = $pedido->detalles()->create($datosLinea);
                } else {
                    continue;
                }
                $vigentes[$detalle->id] = $detalle;
            }
            foreach ($anteriores as $id => $anterior) {
                if (! isset($vigentes[$id])) {
                    $anterior->update(['retirado_en' => now()]);
                }
            }
            $numero = (int) DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->max('numero') + 1;
            $grupos = $this->validarAgrupaciones($datos['agrupaciones'] ?? [], $vigentes, $skus);
            app(DistribuirDespachoUnidades::class)->comprobarEdicion($pedido->id, $grupos, $vigentes);
            $version = DB::table('pedido_versiones')->insertGetId(['pedido_id' => $pedido->id, 'usuario_id' => $usuario->id, 'numero' => $numero,
                'motivo' => $datos['motivo'] ?? 'Registro inicial por unidades físicas.', 'datos' => json_encode(['cabecera' => $cabecera, 'lineas' => array_map(fn ($d): array => $d->getAttributes(), array_values($vigentes)), 'agrupaciones' => $grupos], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            $cabecerasGrupos = [];
            foreach ($grupos as $i => $grupo) {
                $cabecerasGrupos[] = ['pedido_version_id' => $version, 'pedido_id' => $pedido->id, 'numero' => $i + 1, 'capacidad' => $grupo['capacidad'], 'identidad' => $grupo['identidad']];
            }
            foreach (array_chunk($cabecerasGrupos, 100) as $bloque) {
                DB::table('pedido_agrupaciones')->insert($bloque);
            }
            $idsGrupos = $grupos === [] ? collect() : DB::table('pedido_agrupaciones')->where('pedido_version_id', $version)->pluck('id', 'identidad');
            $componentesGrupos = [];
            foreach ($grupos as $grupo) {
                foreach ($grupo['componentes'] as $componente) {
                    $componentesGrupos[] = ['agrupacion_id' => $idsGrupos[$grupo['identidad']], 'pedido_detalle_id' => $componente['detalle_id'], 'cantidad' => $componente['cantidad']];
                }
            }
            foreach (array_chunk($componentesGrupos, 200) as $bloque) {
                DB::table('pedido_agrupacion_detalles')->insert($bloque);
            }
            RegistroAuditoria::registrar('pedido_unidades_versionado', 'pedido', $pedido->id, ['version_anterior' => $numero - 1, 'version_nueva' => $numero, 'motivo' => $datos['motivo'] ?? 'Registro inicial', 'campos' => ['cantidades', 'SKU', 'precios históricos', 'agrupaciones', 'espera']]);
            app(AtenderPedidoUnidades::class)->reconciliarEstado($pedido, $usuario, $datos['motivo'] ?? 'Conciliación con las cantidades vigentes.');

            return $pedido->fresh();
        }, 3);
    }

    public function fotografiasActuales(\Illuminate\Support\Collection $pedidos): array
    {
        $ids = $pedidos->where('modelo_comercial', 'unidades')->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }
        $ultimas = DB::table('pedido_versiones')->whereIn('pedido_id', $ids)->groupBy('pedido_id')->selectRaw('MAX(id) id');

        return DB::table('pedido_versiones')->whereIn('id', $ultimas)->get()->mapWithKeys(fn ($v): array => [$v->pedido_id => json_decode($v->datos, true, 512, JSON_THROW_ON_ERROR)])->all();
    }

    public function historial(Pedido $pedido): LengthAwarePaginator
    {
        return DB::table('pedido_versiones as v')->join('users as u', 'u.id', '=', 'v.usuario_id')
            ->where('v.pedido_id', $pedido->id)->select('v.*', 'u.first_name', 'u.last_name')
            ->orderByDesc('v.numero')->paginate(10, ['*'], 'pagina_version')->withQueryString();
    }

    /** @return array<int, array{descripcion: string, original: int, vigente: int, modalidad: string}> */
    public function cambiosOriginales(Pedido $pedido): array
    {
        $primera = DB::table('pedido_versiones')->where('pedido_id', $pedido->id)->orderBy('numero')->first();
        if (! $primera) {
            return [];
        }
        $originales = collect(json_decode($primera->datos, true, 512, JSON_THROW_ON_ERROR)['lineas'])->keyBy('id');
        $pedido->loadMissing('detalles');
        $vigentes = $pedido->detalles->keyBy('id');
        $cambios = [];
        foreach ($originales->keys()->merge($vigentes->keys())->unique() as $id) {
            $original = $originales->get($id);
            $actual = $vigentes->get($id);
            $antes = (int) ($original['cantidad'] ?? 0);
            $ahora = (int) ($actual->cantidad ?? 0);
            if ($antes !== $ahora) {
                $cambios[] = ['descripcion' => $original['descripcion_producto'] ?? $actual->descripcion_producto,
                    'original' => $antes, 'vigente' => $ahora, 'modalidad' => $original['modalidad'] ?? $actual->modalidad];
            }
        }

        return $cambios;
    }

    /** @param Collection<int, PresentacionProducto> $skus */
    private function validarAgrupaciones(array $grupos, array $detalles, Collection $skus): array
    {
        $porSku = collect($detalles)->keyBy('presentacion_producto_id');
        $asignadas = [];
        $salida = [];
        $identidades = [];
        foreach ($grupos as $grupo) {
            $identidad = strtolower($grupo['identidad'] ?? (string) Str::uuid());
            if (isset($identidades[$identidad])) {
                throw ValidationException::withMessages(['agrupaciones' => 'Cada paquete tiene una identidad propia. Copiar composición crea otro paquete.']);
            }
            $identidades[$identidad] = true;
            $componentes = [];
            $compatibilidad = null;
            $total = 0;
            $vistos = [];
            foreach ($grupo['componentes'] as $c) {
                $skuId = (int) $c['sku_id'];
                $detalle = $porSku->get($skuId);
                $sku = $skus->get($skuId);
                if (! $detalle || ! $sku || isset($vistos[$skuId])) {
                    throw ValidationException::withMessages(['agrupaciones' => 'El paquete contiene un SKU ajeno al pedido o repetido.']);
                }
                $clave = [$sku->producto_id, (string) $sku->contenido, $sku->unidad_medida, $sku->tipo_envase];
                if ($compatibilidad !== null && $clave !== $compatibilidad) {
                    throw ValidationException::withMessages(['agrupaciones' => 'No se agrupan productos, capacidades o envases incompatibles.']);
                }
                $compatibilidad = $clave;
                $vistos[$skuId] = true;
                $cantidad = (int) $c['cantidad'];
                $asignadas[$skuId] = ($asignadas[$skuId] ?? 0) + $cantidad;
                if ($asignadas[$skuId] > (int) $detalle->cantidad) {
                    throw ValidationException::withMessages(['agrupaciones' => 'La distribución supera las unidades vendidas del SKU.']);
                }
                $componentes[] = ['detalle_id' => $detalle->id, 'sku_id' => $skuId, 'cantidad' => $cantidad];
                $total += $cantidad;
            }
            if ($total > (int) $grupo['capacidad']) {
                throw ValidationException::withMessages(['agrupaciones' => 'El contenido del paquete supera su capacidad declarada.']);
            }
            $salida[] = ['identidad' => $identidad, 'capacidad' => (int) $grupo['capacidad'], 'unidades' => $total, 'componentes' => $componentes];
        }

        return $salida;
    }
}
