<?php

namespace App\Services;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ActividadIndicadores
{
    public function filtrar(Builder $q, array $f, string $presentacion = 'p', ?string $insumo = null, ?string $skuHistorico = null): Builder
    {
        foreach (['presentacion_id' => $presentacion.'.id', 'producto_id' => $presentacion.'.producto_id', 'sku' => $skuHistorico === null ? $presentacion.'.codigo_sku' : DB::raw($skuHistorico)] as $campo => $col) {
            if (! empty($f[$campo])) {
                $q->where($col, $f[$campo]);
            }
        }
        if ($insumo !== null && ! empty($f['insumo_id'])) {
            $q->where($insumo, $f['insumo_id']);
        }

        return $q;
    }

    public function consultaMovimientos(array $f, bool $aplicarPeriodo = true): Builder
    {
        $q = DB::table('movimientos_inventario as m')->leftJoin('presentaciones_producto as p', 'p.id', '=', 'm.presentacion_producto_id')->leftJoin('insumos as i', 'i.id', '=', 'm.insumo_id');
        $this->filtrar($q, $f, insumo: 'm.insumo_id');
        if ($aplicarPeriodo) {
            FiltrosIndicadores::periodo($q, 'm.created_at', $f);
        }
        foreach (['tipo' => 'm.tipo', 'origen' => 'm.origen_tipo', 'pedido_id' => 'm.pedido_id'] as $k => $c) {
            if (! empty($f[$k])) {
                $q->where($c, $f[$k]);
            }
        }

        return $q;
    }

    public function movimientos(array $f): \Generator
    {
        foreach ($this->consultaMovimientos($f)->select(['m.*', 'p.codigo_sku', 'i.codigo as codigo_insumo', 'i.unidad_medida'])->lazyById(100, 'm.id', 'id') as $m) {
            yield ['movimiento_id' => $m->id, 'fecha_registro' => $m->created_at, 'pedido_id' => $m->pedido_id, 'presentacion_id' => $m->presentacion_producto_id, 'insumo_id' => $m->insumo_id,
                'sku' => $m->codigo_sku ?? $m->codigo_insumo, 'unidad_medida' => $m->presentacion_producto_id ? 'unidad' : $m->unidad_medida, 'tipo' => $m->tipo, 'origen_tipo' => $m->origen_tipo, 'origen_id' => $m->origen_id,
                'cantidad' => $m->cantidad, 'stock_anterior' => $m->stock_anterior, 'stock_nuevo' => $m->stock_nuevo, 'variacion' => ((float) $m->stock_nuevo < (float) $m->stock_anterior ? '-' : '').CantidadesEnvasado::decimal(abs((int) round(((float) $m->stock_nuevo - (float) $m->stock_anterior) * 1000)))];
        }
    }

    public function saldos_periodo(array $f): \Generator
    {
        $historial = $this->consultaMovimientos($f, aplicarPeriodo: false)
            ->select('m.id')
            ->selectRaw('LAG(m.stock_nuevo) OVER (PARTITION BY m.presentacion_producto_id, m.insumo_id ORDER BY m.id) saldo_previo');
        $q = $this->consultaMovimientos($f)->joinSub($historial, 'cadena', 'cadena.id', '=', 'm.id')
            ->select(['m.presentacion_producto_id', 'm.insumo_id'])
            ->selectRaw('MIN(m.id) primer_id, MAX(m.id) ultimo_id, SUM(m.stock_nuevo-m.stock_anterior) variacion')
            ->selectRaw('SUM(CASE WHEN cadena.saldo_previo IS NOT NULL AND m.stock_anterior <> cadena.saldo_previo THEN 1 ELSE 0 END) saltos')
            ->groupBy('m.presentacion_producto_id', 'm.insumo_id');
        $resumen = DB::query()->fromSub($q, 'r')->join('movimientos_inventario as primero', 'primero.id', '=', 'r.primer_id')->join('movimientos_inventario as ultimo', 'ultimo.id', '=', 'r.ultimo_id')->leftJoin('presentaciones_producto as p', 'p.id', '=', 'r.presentacion_producto_id')->leftJoin('insumos as i', 'i.id', '=', 'r.insumo_id');
        foreach ($resumen->select(['r.*', 'primero.stock_anterior', 'ultimo.stock_nuevo', 'p.codigo_sku', 'i.codigo', 'i.unidad_medida'])->orderBy('r.primer_id')->lazy(100) as $r) {
            yield ['presentacion_id' => $r->presentacion_producto_id, 'insumo_id' => $r->insumo_id, 'sku' => $r->codigo_sku ?? $r->codigo, 'unidad_medida' => $r->presentacion_producto_id ? 'unidad' : $r->unidad_medida,
                'saldo_inicial' => $r->stock_anterior, 'variacion' => $r->variacion, 'saldo_final' => $r->stock_nuevo, 'primer_movimiento_id' => $r->primer_id, 'ultimo_movimiento_id' => $r->ultimo_id,
                'conciliacion' => (int) $r->saltos === 0 && abs((float) $r->stock_anterior + (float) $r->variacion - (float) $r->stock_nuevo) < 0.0005 ? 'Concordante' : 'Revisar continuidad del historial'];
        }
    }

    public function inventario(array $f): \Generator
    {
        if (empty($f['insumo_id'])) {
            $q = DB::table('inventarios as i')->join('presentaciones_producto as p', 'p.id', '=', 'i.presentacion_producto_id');
            $this->filtrar($q, $f);
            foreach ($q->select(['i.id', 'i.presentacion_producto_id', 'i.cantidad_actual', 'p.codigo_sku'])->lazyById(100, 'i.id', 'id') as $r) {
                yield ['tipo_item' => 'Producto terminado', 'presentacion_id' => $r->presentacion_producto_id, 'insumo_id' => null, 'sku' => $r->codigo_sku, 'unidad_medida' => 'unidad', 'stock_actual' => $r->cantidad_actual];
            }
        }
        if (empty($f['producto_id']) && empty($f['presentacion_id']) && empty($f['sku'])) {
            $q = DB::table('insumos');
            if (! empty($f['insumo_id'])) {
                $q->where('id', $f['insumo_id']);
            }
            foreach ($q->lazyById(100) as $r) {
                yield ['tipo_item' => 'Insumo', 'presentacion_id' => null, 'insumo_id' => $r->id, 'sku' => $r->codigo, 'unidad_medida' => $r->unidad_medida, 'stock_actual' => $r->cantidad_actual];
            }
        }
    }

    public function envasados(array $f): \Generator
    {
        foreach ([false, true] as $corregir) {
            $q = DB::table('envasados as e')->join('presentaciones_producto as p', 'p.id', '=', 'e.presentacion_producto_id');
            if ($corregir) {
                $q->join('envasado_correcciones as c', 'c.envasado_id', '=', 'e.id');
            }
            $this->filtrar($q, $f, skuHistorico: $this->skuHistoricoEnvasado());
            FiltrosIndicadores::periodo($q, $corregir ? 'c.created_at' : 'e.created_at', $f);
            if (! empty($f['pedido_id'])) {
                $q->where('e.pedido_id', $f['pedido_id']);
            }
            if (! empty($f['vinculacion'])) {
                $f['vinculacion'] === 'general' ? $q->whereNull('e.pedido_id') : $q->whereNotNull('e.pedido_id');
            }
            $campos = $corregir ? ['c.id', 'c.clasificacion', 'c.cantidad', 'c.created_at', 'e.id as envasado_id'] : ['e.id', 'e.cantidad', 'e.created_at', 'e.id as envasado_id', 'e.unidades_revertidas'];
            foreach ($q->select([...$campos, 'e.presentacion_producto_id', 'e.pedido_id', 'e.presentacion_historica'])->lazyById(100, $corregir ? 'c.id' : 'e.id', 'id') as $r) {
                $historico = json_decode($r->presentacion_historica, true, 512, JSON_THROW_ON_ERROR);
                yield ['envasado_id' => $r->envasado_id, 'correccion_id' => $corregir ? $r->id : null, 'fecha_registro' => $r->created_at, 'pedido_id' => $r->pedido_id, 'presentacion_id' => $r->presentacion_producto_id,
                    'sku' => $historico['sku'] ?? '', 'clasificacion' => $corregir ? $r->clasificacion : 'envasado', 'envasadas_brutas' => $corregir ? 0 : (int) $r->cantidad,
                    'correcciones_administrativas' => $corregir && $r->clasificacion === 'error_administrativo' ? (int) $r->cantidad : 0, 'perdidas_fisicas' => $corregir && $r->clasificacion === 'perdida_fisica' ? (int) $r->cantidad : 0,
                    'efecto_neto' => ($corregir ? -1 : 1) * (int) $r->cantidad, 'neto_actual_envasado' => $corregir ? 0 : (int) $r->cantidad - (int) $r->unidades_revertidas];
            }
        }
    }

    public function materiales_resumen(array $f): \Generator
    {
        $consultas = [];
        foreach ([false, true] as $corregir) {
            $q = DB::table('envasado_consumos as co')->join('envasados as e', 'e.id', '=', 'co.envasado_id')->join('presentaciones_producto as p', 'p.id', '=', 'e.presentacion_producto_id');
            if ($corregir) {
                $q->join('envasado_correccion_materiales as cm', 'cm.envasado_consumo_id', '=', 'co.id')->join('envasado_correcciones as c', 'c.id', '=', 'cm.envasado_correccion_id');
            }
            $this->filtrar($q, $f, insumo: 'co.insumo_id', skuHistorico: $this->skuHistoricoEnvasado());
            FiltrosIndicadores::periodo($q, $corregir ? 'c.created_at' : 'e.created_at', $f);
            if (! empty($f['pedido_id'])) {
                $q->where('e.pedido_id', $f['pedido_id']);
            }
            if (! empty($f['vinculacion'])) {
                $f['vinculacion'] === 'general' ? $q->whereNull('e.pedido_id') : $q->whereNotNull('e.pedido_id');
            }
            $consultas[] = $q->select(['co.insumo_id', 'co.codigo', 'co.unidad_medida'])->selectRaw($corregir ? '0 consumo_bruto, cm.cantidad_devuelta devuelto, cm.cantidad_perdida perdido' : 'co.cantidad consumo_bruto, 0 devuelto, 0 perdido');
        }
        $q = DB::query()->fromSub($consultas[0]->unionAll($consultas[1]), 'm')->groupBy('insumo_id', 'codigo', 'unidad_medida')->select(['insumo_id', 'codigo', 'unidad_medida'])->selectRaw('SUM(consumo_bruto) consumo_bruto, SUM(devuelto) devuelto, SUM(perdido) perdido, SUM(consumo_bruto-devuelto) salida_neta');
        foreach ($q->orderBy('insumo_id')->orderBy('codigo')->orderBy('unidad_medida')->lazy(100) as $r) {
            yield (array) $r;
        }
    }

    public function materiales(array $f): \Generator
    {
        foreach ([false, true] as $corregir) {
            $q = DB::table('envasado_consumos as co')->join('envasados as e', 'e.id', '=', 'co.envasado_id')->join('presentaciones_producto as p', 'p.id', '=', 'e.presentacion_producto_id');
            if ($corregir) {
                $q->join('envasado_correccion_materiales as cm', 'cm.envasado_consumo_id', '=', 'co.id')->join('envasado_correcciones as c', 'c.id', '=', 'cm.envasado_correccion_id');
            }
            $this->filtrar($q, $f, insumo: 'co.insumo_id', skuHistorico: $this->skuHistoricoEnvasado());
            FiltrosIndicadores::periodo($q, $corregir ? 'c.created_at' : 'e.created_at', $f);
            if (! empty($f['pedido_id'])) {
                $q->where('e.pedido_id', $f['pedido_id']);
            }
            if (! empty($f['vinculacion'])) {
                $f['vinculacion'] === 'general' ? $q->whereNull('e.pedido_id') : $q->whereNotNull('e.pedido_id');
            }
            foreach ($q->select(['co.id as consumo_id', 'co.insumo_id', 'co.codigo', 'co.unidad_medida', 'co.funcion', 'e.id as envasado_id', 'e.pedido_id', ...($corregir ? ['cm.id', 'cm.cantidad_devuelta', 'cm.cantidad_perdida', 'c.created_at'] : ['co.id', 'co.cantidad', 'e.created_at'])])->lazyById(100, $corregir ? 'cm.id' : 'co.id', 'id') as $r) {
                $bruto = $corregir ? 0 : CantidadesEnvasado::milesimas($r->cantidad);
                $devuelto = $corregir ? CantidadesEnvasado::milesimas($r->cantidad_devuelta) : 0;
                yield ['consumo_id' => $r->consumo_id, 'correccion_material_id' => $corregir ? $r->id : null, 'envasado_id' => $r->envasado_id, 'pedido_id' => $r->pedido_id, 'insumo_id' => $r->insumo_id, 'codigo' => $r->codigo,
                    'unidad_medida' => $r->unidad_medida, 'funcion' => $r->funcion, 'fecha_registro' => $r->created_at, 'consumo_bruto' => CantidadesEnvasado::decimal($bruto), 'devuelto' => CantidadesEnvasado::decimal($devuelto),
                    'perdido' => $corregir ? $r->cantidad_perdida : '0.000', 'salida_neta' => ($bruto - $devuelto < 0 ? '-' : '').CantidadesEnvasado::decimal(abs($bruto - $devuelto))];
            }
        }
    }

    private function skuHistoricoEnvasado(): string
    {
        $expresion = "JSON_EXTRACT(e.presentacion_historica, '$.sku')";

        return DB::getDriverName() === 'mysql' ? "JSON_UNQUOTE($expresion)" : $expresion;
    }

    private function referencias(): Builder
    {
        return DB::table('incidencias_entrega as inc')->join('pedidos as pe', 'pe.id', '=', 'inc.pedido_id');
    }

    public function entregas(array $f): \Generator
    {
        $historial = DB::table('entrega_pedido_detalles as ed')->select('ed.*')->selectRaw('COALESCE(SUM(aceptadas) OVER (PARTITION BY pedido_detalle_id, referencia_fisica ORDER BY id ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING),0) a_antes, COALESCE(SUM(rechazadas) OVER (PARTITION BY pedido_detalle_id, referencia_fisica ORDER BY id ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING),0) r_antes');
        $foto = DB::table('incidencia_entrega_eventos')->where('tipo', 'despacho_base')->select('incidencia_id')->selectRaw('MAX(id) foto_id, COUNT(*) fotos')->groupBy('incidencia_id');
        $q = $this->referencias()->join('entregas_pedido as e', 'e.incidencia_id', '=', 'inc.id')->joinSub($historial, 'ed', 'ed.entrega_id', '=', 'e.id')
            ->join('pedido_detalles as d', 'd.id', '=', 'ed.pedido_detalle_id')->leftJoin('pedido_despacho_componentes as dc', 'dc.id', '=', 'ed.despacho_componente_id')
            ->leftJoinSub($foto, 'fb', 'fb.incidencia_id', '=', 'inc.id')->leftJoin('incidencia_entrega_eventos as ev', 'ev.id', '=', 'fb.foto_id')
            ->leftJoin('presentaciones_producto as p', 'p.id', '=', DB::raw('COALESCE(dc.presentacion_producto_id,d.presentacion_producto_id)'));
        FiltrosIndicadores::pedido($q, $f);
        FiltrosIndicadores::periodo($q, 'e.ocurrido_en', $f);
        $this->filtrar($q, $f, skuHistorico: 'COALESCE(dc.sku,d.codigo_sku)');
        foreach ($q->select(['ed.*', 'e.ocurrido_en', 'e.created_at', 'e.tipo', 'pe.id as pedido_id', 'ev.datos', 'fb.fotos', 'p.id as presentacion_id'])->lazyById(100, 'ed.id', 'id') as $r) {
            $clave = ControlEntregaPedido::claveFisica((int) $r->pedido_detalle_id, $r->despacho_componente_id === null ? null : (int) $r->despacho_componente_id);
            $foto = (int) $r->fotos === 1 ? (json_decode($r->datos, true, 512, JSON_THROW_ON_ERROR)['lineas'][$clave] ?? null) : null;
            $antes = $this->valorar($foto, (int) $r->a_antes, (int) $r->r_antes);
            $despues = $this->valorar($foto, (int) $r->a_antes + (int) $r->aceptadas, (int) $r->r_antes + (int) $r->rechazadas);
            yield ['entrega_detalle_id' => $r->id, 'pedido_id' => $r->pedido_id, 'linea_id' => $r->pedido_detalle_id, 'despacho_componente_id' => $r->despacho_componente_id, 'presentacion_id' => $r->presentacion_id, 'sku' => $foto['sku'] ?? null,
                'tipo' => $r->tipo, 'fecha_hecho' => $r->ocurrido_en, 'fecha_registro' => $r->created_at, 'aceptadas' => (int) $r->aceptadas, 'rechazadas' => (int) $r->rechazadas,
                'aceptado_centimos' => $antes === null || $despues === null ? null : $despues[0] - $antes[0], 'rechazado_centimos' => $antes === null || $despues === null ? null : $despues[1] - $antes[1], 'fuente' => $foto ? 'Fotografía del despacho' : 'Sin desglose verificable'];
        }
    }

    public function valorar(?array $foto, int $a, int $r): ?array
    {
        $n = $foto['despachadas'] ?? 0;
        if (! $n || (isset($foto['verificada']) && ! $foto['verificada']) || $a < 0 || $r < 0 || $a + $r > $n) {
            return null;
        }
        $b = (int) $foto['importe_centimos'];
        $aceptado = intdiv($b * $a, $n);

        return [$aceptado, intdiv($b * ($a + $r), $n) - $aceptado];
    }

    private function jsonNumero(string $columna, string $clave): Expression
    {
        $expresion = "JSON_EXTRACT($columna, '$.$clave')";

        return DB::raw(DB::getDriverName() === 'mysql' ? "CAST(JSON_UNQUOTE($expresion) AS UNSIGNED)" : "CAST($expresion AS INTEGER)");
    }

    public function retornos(array $f): \Generator
    {
        foreach ([false, true] as $clasificar) {
            $q = $this->referencias()->join('retornos_pedido as r', 'r.incidencia_id', '=', 'inc.id')->join('retorno_pedido_detalles as rd', 'rd.retorno_id', '=', 'r.id')
                ->join('pedido_detalles as d', 'd.id', '=', 'rd.pedido_detalle_id')->leftJoin('pedido_despacho_componentes as dc', 'dc.id', '=', 'rd.despacho_componente_id')
                ->leftJoin('presentaciones_producto as p', 'p.id', '=', DB::raw('COALESCE(dc.presentacion_producto_id,d.presentacion_producto_id)'));
            if ($clasificar) {
                $q->join('incidencia_entrega_eventos as ev', 'rd.id', '=', $this->jsonNumero('ev.datos', 'retorno_detalle_id'))->where('ev.tipo', 'incidencia_clasificar')->whereColumn('ev.incidencia_id', 'inc.id');
            }
            FiltrosIndicadores::pedido($q, $f);
            $this->filtrar($q, $f, skuHistorico: 'COALESCE(dc.sku,d.codigo_sku)');
            FiltrosIndicadores::periodo($q, $clasificar ? 'ev.created_at' : 'r.ocurrido_en', $f);
            foreach ($q->select(['rd.*', 'pe.id as pedido_id', 'r.ocurrido_en', 'r.created_at', DB::raw('COALESCE(dc.sku,d.codigo_sku) sku'), ...($clasificar ? ['ev.id as evento_id', 'ev.datos', 'ev.created_at as clasificada_en'] : [])])->lazyById(100, $clasificar ? 'ev.id' : 'rd.id', $clasificar ? 'evento_id' : 'id') as $r) {
                $datos = $clasificar ? json_decode($r->datos, true, 512, JSON_THROW_ON_ERROR) : [];
                yield ['pedido_id' => $r->pedido_id, 'linea_id' => $r->pedido_detalle_id, 'despacho_componente_id' => $r->despacho_componente_id, 'retorno_id' => $r->retorno_id, 'retorno_detalle_id' => $r->id, 'evento_id' => $r->evento_id ?? null, 'sku' => $r->sku,
                    'tipo_operacion' => $clasificar ? 'Clasificación posterior' : 'Recepción / declaración', 'fecha_hecho' => $clasificar ? $r->clasificada_en : $r->ocurrido_en, 'fecha_registro' => $clasificar ? $r->clasificada_en : $r->created_at,
                    'recibidas' => $clasificar ? 0 : (int) $r->aptas + (int) $r->danadas + (int) $r->en_revision, 'aptas' => $clasificar ? (int) $datos['aptas'] : (int) $r->aptas,
                    'danadas' => $clasificar ? (int) $datos['danadas'] : (int) $r->danadas, 'en_revision' => $clasificar ? -(int) $datos['aptas'] - (int) $datos['danadas'] : (int) $r->en_revision,
                    'no_recuperadas' => $clasificar ? 0 : (int) $r->no_recuperadas];
            }
        }
    }

    public function aprobaciones(array $f): \Generator
    {
        $movimientos = DB::table('movimientos_inventario')->where('origen_tipo', 'retorno_pedido')->where('tipo', 'entrada')->groupBy('origen_id')->select('origen_id')
            ->selectRaw('COUNT(*) registros, MIN(id) id, MIN(presentacion_producto_id) presentacion_producto_id, SUM(cantidad) cantidad, SUM(stock_nuevo-stock_anterior) variacion');
        $q = $this->referencias()->join('incidencia_entrega_eventos as ev', 'ev.incidencia_id', '=', 'inc.id')->where('ev.tipo', 'retorno_aprobado')
            ->join('retorno_pedido_detalles as rd', 'rd.id', '=', $this->jsonNumero('ev.datos', 'retorno_detalle_id'))
            ->join('retornos_pedido as retorno', 'retorno.id', '=', 'rd.retorno_id')
            ->join('pedido_detalles as d', 'd.id', '=', 'rd.pedido_detalle_id')->leftJoin('pedido_despacho_componentes as dc', 'dc.id', '=', 'rd.despacho_componente_id')
            ->leftJoinSub($movimientos, 'm', 'm.origen_id', '=', DB::raw('CAST(ev.id AS CHAR)'))->leftJoin('presentaciones_producto as p', 'p.id', '=', DB::raw('COALESCE(dc.presentacion_producto_id,d.presentacion_producto_id)'));
        FiltrosIndicadores::pedido($q, $f);
        $this->filtrar($q, $f, skuHistorico: 'COALESCE(dc.sku,d.codigo_sku)');
        if (empty($f['_acumulado_actual'])) {
            FiltrosIndicadores::periodo($q, 'ev.created_at', $f);
        }
        foreach ($q->select(['ev.id', 'ev.created_at', 'ev.datos', 'pe.id as pedido_id', 'rd.id as retorno_detalle_id', 'rd.pedido_detalle_id', 'rd.despacho_componente_id', 'retorno.incidencia_id as retorno_incidencia', 'inc.id as incidencia_id', 'm.id as movimiento_id', 'm.registros', 'm.variacion', 'm.cantidad', 'm.presentacion_producto_id', 'p.id as presentacion_esperada', DB::raw('COALESCE(dc.sku,d.codigo_sku) sku')])->lazyById(100, 'ev.id', 'id') as $r) {
            $d = json_decode($r->datos, true, 512, JSON_THROW_ON_ERROR);
            $verificada = (int) $r->registros === 1 && (int) $r->retorno_incidencia === (int) $r->incidencia_id && (int) $r->presentacion_producto_id === (int) $r->presentacion_esperada && (float) $r->cantidad === (float) $d['cantidad'] && (float) $r->variacion === (float) $r->cantidad && (float) $r->cantidad > 0;
            yield ['evento_id' => $r->id, 'pedido_id' => $r->pedido_id, 'linea_id' => $r->pedido_detalle_id, 'despacho_componente_id' => $r->despacho_componente_id, 'retorno_detalle_id' => $r->retorno_detalle_id, 'sku' => $r->sku,
                'fecha_registro' => $r->created_at, 'movimiento_id' => $r->movimiento_id, 'reincorporadas' => $verificada ? (int) $r->cantidad : null, 'verificable' => $verificada ? 'Sí' : 'No: falta concordancia con el movimiento'];
        }
    }
}
