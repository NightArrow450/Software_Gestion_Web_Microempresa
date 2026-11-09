<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AtenderPedidoUnidades
{
    public const INCIDENCIAS = ['liquido' => 'Falta líquido preparado', 'envases' => 'Faltan envases', 'etiquetas_tapas' => 'Faltan etiquetas o tapas', 'insumos_quimicos' => 'Faltan insumos químicos', 'tiempo' => 'Falta tiempo para preparar', 'otro' => 'Otra incidencia'];

    public function historialPreparacion(Pedido $pedido): LengthAwarePaginator
    {
        return DB::table('pedido_preparaciones as p')->join('users as u', 'u.id', '=', 'p.usuario_id')
            ->join('pedido_detalles as d', 'd.id', '=', 'p.pedido_detalle_id')->where('p.pedido_id', $pedido->id)
            ->select('p.*', 'u.first_name', 'u.last_name', 'd.codigo_sku')->orderByDesc('p.id')->paginate(10, ['*'], 'pagina_preparacion')->withQueryString();
    }

    public function despachadas(int $pedidoId): Collection
    {
        return DB::table('pedido_despacho_lineas as d')->join('pedido_despachos as s', 's.id', '=', 'd.despacho_id')
            ->where('s.pedido_id', $pedidoId)->groupBy('d.detalle_comercial_id')
            ->select('d.detalle_comercial_id')->selectRaw('SUM(d.cantidad) cantidad')->pluck('cantidad', 'detalle_comercial_id');
    }

    public function pendientes(Pedido $pedido): int
    {
        $despachadas = $this->despachadas($pedido->id);

        return (int) $pedido->detalles()->get()->sum(fn ($d): int => max(0, (int) $d->cantidad - (int) $despachadas->get($d->id, 0)));
    }

    public function validarCierre(Pedido $pedido): void
    {
        $lineas = $pedido->detalles()->get();
        $salidas = $this->despachadas($pedido->id);
        $preparadas = DB::table('pedido_preparaciones')->where('pedido_id', $pedido->id)->orderBy('id')->get()->keyBy('pedido_detalle_id');
        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages(['estado' => 'El pedido no tiene unidades vigentes para preparar.']);
        }
        foreach ($lineas as $d) {
            $p = $preparadas->get($d->id);
            $despachadas = (int) $salidas->get($d->id, 0);
            $preparadasRestantes = $p ? max(0, (int) $p->cantidad - $despachadas + (int) $p->despachadas_base) : 0;
            if ((int) $d->cantidad_espera > 0 || $preparadasRestantes < (int) $d->cantidad - $despachadas) {
                throw ValidationException::withMessages(['estado' => 'Hay unidades pendientes o en espera. Puedes registrar preparación parcial y despachar solo lo preparado.']);
            }
        }
    }

    public function reconciliarEstado(Pedido $pedido, User $usuario, string $motivo): void
    {
        if (! DB::table('pedido_despachos')->where('pedido_id', $pedido->id)->exists()) {
            return;
        }
        $lineas = app(ControlEntregaPedido::class)->resumen($pedido)['lineas'];
        $aceptadas = array_sum(array_column($lineas, 'aceptadas'));
        $rechazadas = array_sum(array_column($lineas, 'rechazadas'));
        $estado = $this->pendientes($pedido) > 0 ? 'despacho_parcial'
            : (array_sum(array_column($lineas, 'pendientes')) > 0 ? ($aceptadas + $rechazadas > 0 ? 'entrega_pendiente' : 'despachado')
                : ($rechazadas === 0 ? 'entregado' : ($aceptadas === 0 ? 'rechazado' : 'entrega_parcial')));
        if ($estado !== $pedido->estado) {
            $anterior = $pedido->estado;
            $pedido->update(['estado' => $estado]);
            $pedido->historialEstados()->create(['usuario_id' => $usuario->id, 'estado_anterior' => $anterior, 'estado_nuevo' => $estado, 'motivo' => $motivo]);
        }
    }

    public function preparar(Pedido $pedido, array $entrada, User $usuario): void
    {
        abort_unless($usuario->status && $usuario->role?->tienePermiso('cumplimiento.preparar'), 403);
        $datos = Validator::make($entrada, [
            'version_pedido' => ['required', 'integer', 'min:0'], 'pedido_detalle_id' => ['required', 'integer'],
            'cantidad' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'incidencia' => ['nullable', Rule::in(array_keys(self::INCIDENCIAS))],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ])->validate();
        DB::transaction(function () use ($pedido, $datos, $usuario): void {
            $actual = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $this->validarVersion($actual, (int) $datos['version_pedido']);
            if (! in_array($actual->estado, ['confirmado', 'en_preparacion', 'despacho_parcial', 'listo_despacho'], true)) {
                throw ValidationException::withMessages(['estado' => 'El pedido no admite preparación.']);
            }
            $detalle = $actual->detalles()->whereKey($datos['pedido_detalle_id'])->first();
            $salida = (int) $this->despachadas($actual->id)->get($datos['pedido_detalle_id'], 0);
            if (! $detalle || (int) $datos['cantidad'] > (int) $detalle->cantidad - $salida - (int) $detalle->cantidad_espera) {
                throw ValidationException::withMessages(['cantidad' => 'La cantidad preparada supera las unidades pendientes activas de la línea.']);
            }
            $stock = (int) Inventario::where('presentacion_producto_id', $detalle->presentacion_producto_id)->value('cantidad_actual');
            if ($stock < (int) $datos['cantidad']) {
                throw ValidationException::withMessages(['cantidad' => 'Primero confirma el envasado físico o revisa el stock real. Preparar no crea productos.']);
            }
            DB::table('pedido_preparaciones')->insert(['pedido_id' => $actual->id, 'pedido_detalle_id' => $detalle->id,
                'usuario_id' => $usuario->id, 'version_comercial' => (int) DB::table('pedido_versiones')->where('pedido_id', $actual->id)->max('numero'), 'cantidad' => $datos['cantidad'], 'despachadas_base' => $salida,
                'incidencia' => $datos['incidencia'] ?? null, 'motivo' => $datos['motivo'], 'created_at' => now()]);
            $actual->update(['estado' => $salida > 0 || $actual->estado === 'despacho_parcial' ? 'despacho_parcial' : 'en_preparacion']);
            RegistroAuditoria::registrar('preparacion_real_registrada', 'pedido', $actual->id, ['cantidad' => $datos['cantidad'], 'motivo' => $datos['motivo']]);
        }, 3);
    }

    public function despachar(Pedido $pedido, array $entrada, User $usuario): int
    {
        abort_unless($usuario->status && $usuario->role?->tienePermiso('cumplimiento.despachar'), 403);
        $datos = Validator::make($entrada, ['version_pedido' => ['required', 'integer', 'min:0'], 'operacion' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]{16,64}$/'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'], 'paquetes' => ['sometimes', 'array', 'max:1000'], 'paquetes.*' => ['required', 'uuid', 'distinct'], 'detalles' => ['required', 'array', 'min:1', 'max:18'],
            'detalles.*.id' => ['required', 'integer', 'distinct'], 'detalles.*.cantidad' => ['required', 'integer', 'min:0', 'max:4294967295']])->validate();
        $huella = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($pedido, $datos, $usuario, $huella): int {
            $actual = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $anterior = DB::table('pedido_despachos')->where('operacion', $datos['operacion'])->first();
            if ($anterior) {
                if ((int) $anterior->pedido_id !== $actual->id || (int) $anterior->usuario_id !== $usuario->id || $anterior->huella !== $huella) {
                    throw ValidationException::withMessages(['operacion' => 'La operación ya fue utilizada con otro contenido.']);
                }

                return (int) $anterior->id;
            }
            $this->validarVersion($actual, (int) $datos['version_pedido']);
            if (! in_array($actual->estado, ['en_preparacion', 'listo_despacho', 'despacho_parcial'], true)) {
                throw ValidationException::withMessages(['estado' => 'El pedido no está en preparación o no conserva pendientes despachables.']);
            }
            $detalles = $actual->detalles()->lockForUpdate()->get()->keyBy('id');
            $despachadas = $this->despachadas($actual->id);
            $preparadas = DB::table('pedido_preparaciones')->where('pedido_id', $actual->id)->orderBy('id')->get()->keyBy('pedido_detalle_id');
            $lineas = [];
            $cantidades = [];
            foreach ($datos['detalles'] as $fila) {
                $d = $detalles->get((int) $fila['id']);
                if (! $d) {
                    throw ValidationException::withMessages(['detalles' => 'Una línea no pertenece al pedido vigente.']);
                }
                $n = (int) $fila['cantidad'];
                if ($n === 0) {
                    continue;
                }
                $restante = (int) $d->cantidad - (int) $despachadas->get($d->id, 0) - (int) $d->cantidad_espera;
                $preparacion = $preparadas->get($d->id);
                $consumidasDesdePreparacion = $preparacion ? (int) $despachadas->get($d->id, 0) - (int) $preparacion->despachadas_base : 0;
                $preparadasDisponibles = max(0, (int) ($preparacion->cantidad ?? 0) - $consumidasDesdePreparacion);
                if ($n > $restante || $n > $preparadasDisponibles) {
                    throw ValidationException::withMessages(['detalles' => 'Solo se despachan unidades pendientes confirmadas como físicamente preparadas.']);
                }
                $lineas[] = [$d, $n];
                $skuId = (int) $d->presentacion_producto_id;
                $cantidades[$skuId] = ($cantidades[$skuId] ?? 0) + $n;
            }
            if ($lineas === []) {
                throw ValidationException::withMessages(['detalles' => 'Indica al menos una unidad realmente preparada.']);
            }
            $distribucion = app(DistribuirDespachoUnidades::class)->preparar($actual, $datos['paquetes'] ?? [], $cantidades);
            $stock = Inventario::whereIn('presentacion_producto_id', array_keys($cantidades))->orderBy('presentacion_producto_id')->lockForUpdate()->get()->keyBy('presentacion_producto_id');
            foreach ($cantidades as $id => $cantidad) {
                if (! $stock->has($id) || (int) $stock->get($id)->cantidad_actual < $cantidad) {
                    throw ValidationException::withMessages(['inventario' => 'Stock insuficiente. Otra operación pudo utilizar las existencias; no se realizó ningún descuento.']);
                }
            }
            $version = DB::table('pedido_versiones')->where('pedido_id', $actual->id)->orderByDesc('numero')->first();
            if ($version === null) {
                throw ValidationException::withMessages(['pedido' => 'Falta la versión comercial verificable del pedido. No se registró la salida.']);
            }
            $despacho = DB::table('pedido_despachos')->insertGetId(['pedido_id' => $actual->id, 'usuario_id' => $usuario->id, 'version_comercial' => $version->numero,
                'operacion' => $datos['operacion'], 'huella' => $huella, 'distribucion' => json_encode($distribucion, JSON_THROW_ON_ERROR), 'motivo' => $datos['motivo'], 'created_at' => now()]);
            $idsGrupos = $distribucion['paquetes'] === [] ? collect() : DB::table('pedido_agrupaciones')->where('pedido_version_id', $version->id)->pluck('id', 'identidad');
            $enlacesGrupos = [];
            foreach ($distribucion['paquetes'] as $paquete) {
                $enlacesGrupos[] = ['despacho_id' => $despacho, 'pedido_id' => $actual->id, 'pedido_agrupacion_id' => $idsGrupos[$paquete['identidad']], 'identidad' => $paquete['identidad']];
            }
            foreach (array_chunk($enlacesGrupos, 100) as $bloque) {
                DB::table('pedido_despacho_agrupaciones')->insert($bloque);
            }
            foreach ($cantidades as $id => $cantidad) {
                $inventario = $stock->get($id);
                $antes = (int) $inventario->cantidad_actual;
                $inventario->update(['cantidad_actual' => $antes - $cantidad]);
                MovimientoInventario::create(['presentacion_producto_id' => $id, 'pedido_id' => null, 'despacho_unidades_id' => $despacho,
                    'usuario_id' => $usuario->id, 'origen_tipo' => 'despacho_unidades', 'origen_id' => (string) $despacho,
                    'tipo' => 'salida', 'cantidad' => $cantidad, 'stock_anterior' => $antes, 'stock_nuevo' => $antes - $cantidad,
                    'motivo' => 'Salida física del despacho '.$despacho.' del pedido '.$actual->numero.'.']);
            }
            foreach ($lineas as [$d, $n]) {
                $fisico = $actual->detalles()->create(['presentacion_producto_id' => $d->presentacion_producto_id, 'codigo_sku' => $d->codigo_sku,
                    'descripcion_producto' => $d->descripcion_producto, 'tipo_linea' => 'normal', 'modalidad' => 'unidad', 'cantidad' => $n,
                    'precio_unitario' => $d->precio_unitario, 'importe' => app(CalculadoraPedido::class)->calcularImporte($n, $d->precio_unitario), 'retirado_en' => now()]);
                DB::table('pedido_despacho_lineas')->insert(['despacho_id' => $despacho, 'detalle_comercial_id' => $d->id, 'detalle_fisico_id' => $fisico->id,
                    'presentacion_producto_id' => $d->presentacion_producto_id, 'cantidad' => $n, 'precio_unitario' => $d->precio_unitario]);
            }
            $previo = $actual->estado;
            $estado = $this->pendientes($actual) > 0 ? 'despacho_parcial' : 'despachado';
            $actual->update(['estado' => $estado]);
            $actual->historialEstados()->create(['usuario_id' => $usuario->id, 'estado_anterior' => $previo, 'estado_nuevo' => $estado, 'motivo' => $datos['motivo']]);
            $control = app(ControlEntregaPedido::class);
            $incidencia = $control->bloquear($actual, usuarioBase: $usuario->id);
            $foto = $this->fotografia($actual, $despacho);
            $control->evento($incidencia->id, $usuario->id, 'despacho_unidades', $datos['motivo'], ['despacho_id' => $despacho, 'lineas' => $foto]);
            $control->avanzar($incidencia->id, 'despacho_unidades_registrado', ['pedido_id' => $actual->id]);

            return $despacho;
        }, 3);
    }

    public function fotografia(Pedido $pedido, ?int $despacho = null): array
    {
        $filas = DB::table('pedido_despacho_lineas as l')->join('pedido_despachos as s', 's.id', '=', 'l.despacho_id')
            ->join('pedido_detalles as d', 'd.id', '=', 'l.detalle_fisico_id')->where('s.pedido_id', $pedido->id)
            ->when($despacho, fn ($q) => $q->where('s.id', $despacho))->select('d.*', 's.id as despacho_id')->orderBy('l.id')->get();
        $foto = [];
        $salidas = DB::table('movimientos_inventario')->whereIn('despacho_unidades_id', $filas->pluck('despacho_id'))->get()->groupBy(fn ($m): string => $m->despacho_unidades_id.'|'.$m->presentacion_producto_id);
        $porSku = $filas->groupBy(fn ($d): string => $d->despacho_id.'|'.$d->presentacion_producto_id);
        foreach ($filas as $d) {
            $clave = $d->despacho_id.'|'.$d->presentacion_producto_id;
            $movimientos = $salidas->get($clave, collect());
            $movimiento = $movimientos->first();
            $verificada = $movimientos->count() === 1 && $movimiento->tipo === 'salida' && $movimiento->origen_tipo === 'despacho_unidades'
                && (string) $movimiento->origen_id === (string) $d->despacho_id && (int) $movimiento->cantidad === (int) $porSku->get($clave)->sum('cantidad')
                && (int) $movimiento->stock_anterior - (int) $movimiento->stock_nuevo === (int) $movimiento->cantidad;
            $foto[$d->id] = ['id' => $d->id, 'presentacion_id' => $d->presentacion_producto_id, 'sku' => $d->codigo_sku,
                'descripcion' => $d->descripcion_producto.' · Despacho '.$d->despacho_id, 'modalidad' => 'unidad', 'cantidad_original' => (int) $d->cantidad,
                'factor' => 1, 'despachadas' => (int) $d->cantidad, 'importe_centimos' => (int) round((float) $d->importe * 100), 'verificada' => $verificada];
        }

        return $foto;
    }

    private function validarVersion(Pedido $pedido, int $version): void
    {
        if ($pedido->modelo_comercial !== 'unidades' || (int) $pedido->version !== $version) {
            throw ValidationException::withMessages(['version_pedido' => 'El pedido cambió o pertenece al modelo histórico. Recarga antes de continuar.']);
        }
    }
}
