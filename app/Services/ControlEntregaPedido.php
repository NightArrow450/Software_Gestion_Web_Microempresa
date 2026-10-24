<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use stdClass;

class ControlEntregaPedido
{
    public const ESTADOS = ['despachado', 'entrega_pendiente', 'entrega_parcial', 'rechazado', 'entregado'];

    public function disponible(): bool
    {
        $tablas = ['incidencias_entrega', 'entregas_pedido', 'entrega_pedido_detalles', 'retornos_pedido', 'retorno_pedido_detalles', 'incidencia_entrega_eventos'];

        $esquema = DB::getDriverName() === 'sqlite' ? null : DB::getDatabaseName();

        return array_diff($tablas, Schema::getTableListing($esquema, false)) === [];
    }

    public function autorizar(User $usuario, string $permiso): void
    {
        abort_unless($usuario->status && $usuario->role?->tienePermiso($permiso), 403);
    }

    public function bloquear(Pedido $pedido, ?int $version = null, bool $cerrada = false, ?int $usuarioBase = null): stdClass
    {
        DB::table('incidencias_entrega')->insertOrIgnore(['pedido_id' => $pedido->id, 'version' => 0, 'estado_revision' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
        $incidencia = DB::table('incidencias_entrega')->where('pedido_id', $pedido->id)->lockForUpdate()->first();
        if (($version !== null && $version !== (int) $incidencia->version) || (! $cerrada && $incidencia->resuelto_en !== null)) {
            throw ValidationException::withMessages(['version' => 'El expediente cambió o está cerrado. Recarga la página antes de registrar otra operación.']);
        }
        if (! DB::table('incidencia_entrega_eventos')->where('incidencia_id', $incidencia->id)->where('tipo', 'despacho_base')->lockForUpdate()->first()) {
            $this->evento($incidencia->id, $usuarioBase ?? $pedido->despachado_por_id ?? $pedido->usuario_id, 'despacho_base', 'Fotografía de cantidades originales; no modifica el despacho.', ['lineas' => $this->fotografia($pedido, true)]);
        }

        return $incidencia;
    }

    public function fotografiarDespacho(Pedido $pedido, int $usuarioId): void
    {
        if (! $this->disponible()) {
            return;
        }
        $this->bloquear($pedido, usuarioBase: $usuarioId);
    }

    /** @return array<int, array<string, mixed>> */
    public function fotografia(Pedido $pedido, bool $actual = false): array
    {
        $detalles = $pedido->detalles()->with('presentacion')->when($actual, fn ($q) => $q->lockForUpdate())->get();
        $salidas = DB::table('movimientos_inventario')->where('pedido_id', $pedido->id)->where('tipo', 'salida')->when($actual, fn ($q) => $q->lockForUpdate())->get()->keyBy('presentacion_producto_id');
        $lineas = [];
        foreach ($detalles as $detalle) {
            if ($detalle->tipo_linea === 'surtido') {
                $componentes = $actual && $pedido->estado === 'listo_despacho'
                    ? app(GestionarSurtidos::class)->fotografiar($detalle)
                    : DB::table('pedido_despacho_componentes')->where('pedido_detalle_id', $detalle->id)->orderBy('id')->get()->all();
                if ($componentes === []) {
                    $lineas[$detalle->id] = ['id' => $detalle->id, 'presentacion_id' => null, 'sku' => '', 'descripcion' => $detalle->descripcion_producto, 'modalidad' => 'empaque', 'cantidad_original' => (int) $detalle->cantidad, 'factor' => $detalle->unidades_por_empaque, 'despachadas' => null, 'importe_centimos' => (int) round((float) $detalle->importe * 100), 'verificada' => false];
                }
                foreach ($componentes as $c) {
                    $lineas[self::claveFisica($detalle->id, $c->id)] = ['id' => $detalle->id, 'componente_id' => $c->id, 'presentacion_id' => $c->presentacion_producto_id, 'sku' => $c->sku, 'descripcion' => $c->descripcion.' · Paquete '.$c->numero_paquete, 'modalidad' => 'unidad', 'cantidad_original' => (int) $c->cantidad, 'factor' => 1, 'despachadas' => (int) $c->cantidad, 'importe_centimos' => (int) $c->importe_centimos, 'verificada' => false];
                }

                continue;
            }
            $factor = $detalle->modalidad === 'unidad' ? 1 : $detalle->unidades_por_empaque;
            // Solo un despacho nuevo puede fijar el respaldo del catálogo en ese mismo instante.
            if ($factor === null && $pedido->estado === 'listo_despacho') {
                $factor = PresentacionProducto::whereKey($detalle->presentacion_producto_id)->lockForUpdate()->value('unidades_por_empaque');
            }
            $unidades = in_array($detalle->modalidad, ['unidad', 'empaque'], true) && $factor > 0 ? (int) $detalle->cantidad * (int) $factor : null;
            $lineas[$detalle->id] = ['id' => $detalle->id, 'presentacion_id' => $detalle->presentacion_producto_id, 'sku' => $detalle->codigo_sku, 'descripcion' => $detalle->descripcion_producto, 'modalidad' => $detalle->modalidad, 'cantidad_original' => (int) $detalle->cantidad, 'factor' => $factor, 'despachadas' => $unidades, 'importe_centimos' => (int) round((float) $detalle->importe * 100), 'verificada' => false];
        }
        foreach ($lineas as &$linea) {
            $mismoSku = array_filter($lineas, fn (array $otra): bool => $otra['presentacion_id'] === $linea['presentacion_id']);
            $conocidas = count(array_filter($mismoSku, fn (array $otra): bool => $otra['despachadas'] !== null)) === count($mismoSku);
            $salida = $salidas->get($linea['presentacion_id']);
            $linea['verificada'] = $linea['presentacion_id'] !== null && $conocidas && $salida !== null && (int) $salida->cantidad === array_sum(array_column($mismoSku, 'despachadas'));
        }
        unset($linea);

        return $lineas;
    }

    /** @return array<string, mixed> */
    public function resumen(Pedido $pedido, bool $actual = false): array
    {
        $incidencia = DB::table('incidencias_entrega')->where('pedido_id', $pedido->id)->when($actual, fn ($q) => $q->lockForUpdate())->first();
        $base = $incidencia ? DB::table('incidencia_entrega_eventos')->where('incidencia_id', $incidencia->id)->where('tipo', 'despacho_base')->when($actual, fn ($q) => $q->lockForUpdate())->first() : null;
        $lineas = $base ? json_decode($base->datos, true, 512, JSON_THROW_ON_ERROR)['lineas'] : $this->fotografia($pedido);
        $resultados = $incidencia ? DB::table('entrega_pedido_detalles as d')->join('entregas_pedido as e', 'e.id', '=', 'd.entrega_id')->where('e.incidencia_id', $incidencia->id)->select('d.*')->when($actual, fn ($q) => $q->lockForUpdate())->get()->groupBy(fn ($r): int|string => self::claveFisica($r->pedido_detalle_id, $r->despacho_componente_id ?? null)) : collect();
        $retornos = $incidencia ? DB::table('retorno_pedido_detalles as d')->join('retornos_pedido as r', 'r.id', '=', 'd.retorno_id')->where('r.incidencia_id', $incidencia->id)->select('d.*', 'r.ocurrido_en')->when($actual, fn ($q) => $q->lockForUpdate())->get() : collect();
        foreach ($lineas as &$linea) {
            $datos = $resultados->get(self::claveFisica($linea['id'], $linea['componente_id'] ?? null), collect());
            $recibidas = $retornos->filter(fn ($r): bool => self::claveFisica($r->pedido_detalle_id, $r->despacho_componente_id ?? null) === self::claveFisica($linea['id'], $linea['componente_id'] ?? null));
            $linea['aceptadas'] = (int) $datos->sum('aceptadas');
            $linea['rechazadas'] = (int) $datos->sum('rechazadas');
            $linea['pendientes'] = $linea['despachadas'] === null ? null : $linea['despachadas'] - $linea['aceptadas'] - $linea['rechazadas'];
            $linea['retornadas'] = (int) $recibidas->sum(fn ($r): int => $r->aptas + $r->danadas + $r->en_revision);
            $linea['aptas'] = (int) $recibidas->sum(fn ($r): int => $r->aptas + $r->revisadas_aptas);
            $linea['danadas'] = (int) $recibidas->sum(fn ($r): int => $r->danadas + $r->revisadas_danadas);
            $linea['por_revisar'] = (int) $recibidas->sum(fn ($r): int => $r->en_revision - $r->revisadas_aptas - $r->revisadas_danadas);
            $linea['no_recuperadas'] = (int) $recibidas->sum('no_recuperadas');
            $linea['reincorporadas'] = (int) $recibidas->sum('reincorporadas');
            $linea['por_retornar'] = $linea['rechazadas'] - $linea['retornadas'] - $linea['no_recuperadas'];
            $linea['por_aprobar'] = $linea['aptas'] - $linea['reincorporadas'];
            $u = $linea['despachadas'];
            $linea['importe_aceptado_centimos'] = $u ? intdiv($linea['importe_centimos'] * $linea['aceptadas'], $u) : null;
            $linea['importe_rechazado_centimos'] = $u ? intdiv($linea['importe_centimos'] * ($linea['aceptadas'] + $linea['rechazadas']), $u) - $linea['importe_aceptado_centimos'] : null;
            $linea['importe_pendiente_centimos'] = $u ? $linea['importe_centimos'] - $linea['importe_aceptado_centimos'] - $linea['importe_rechazado_centimos'] : null;
        }
        unset($linea);
        $eventos = $incidencia && ! $actual ? DB::table('incidencia_entrega_eventos as e')->join('users as u', 'u.id', '=', 'e.usuario_id')->where('e.incidencia_id', $incidencia->id)->orderBy('e.id')->get(['e.*', 'u.first_name', 'u.last_name']) : collect();

        return ['historico_sin_desglose' => $pedido->estado === 'entregado' && $resultados->isEmpty(), 'incidencia' => $incidencia, 'version' => (int) ($incidencia->version ?? 0), 'lineas' => $lineas, 'retornos' => $retornos, 'eventos' => $eventos];
    }

    public static function claveFisica(int $detalleId, ?int $componenteId = null): int|string
    {
        return $componenteId === null ? $detalleId : 'surtido:'.$componenteId;
    }

    public function evento(int $incidenciaId, int $usuarioId, string $tipo, string $motivo, array $datos): int
    {
        return DB::table('incidencia_entrega_eventos')->insertGetId(['incidencia_id' => $incidenciaId, 'usuario_id' => $usuarioId, 'tipo' => $tipo, 'motivo' => $motivo, 'datos' => json_encode($datos, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function fechaDelHecho(?string $fecha): string
    {
        return ($fecha === null ? CarbonImmutable::now('America/Lima') : CarbonImmutable::parse($fecha, 'America/Lima')->setTimezone('America/Lima'))->toDateTimeString();
    }

    public function avanzar(int $incidenciaId, string $accion, array $detalle = []): void
    {
        DB::table('incidencias_entrega')->where('id', $incidenciaId)->update(['version' => DB::raw('version + 1'), 'updated_at' => now()]);
        RegistroAuditoria::registrar($accion, 'incidencia_entrega', $incidenciaId, $detalle + ['campos' => ['expediente', 'cantidades', 'historial']]);
    }
}
