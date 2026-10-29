<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PedidosIndicadores
{
    public function lineas(array $f, bool $periodo = true): Builder
    {
        $q = DB::table('pedido_detalles as d')->join('pedidos as pe', 'pe.id', '=', 'd.pedido_id')
            ->leftJoin('presentaciones_producto as p', 'p.id', '=', 'd.presentacion_producto_id')
            ->leftJoin('formatos_surtidos as fs', 'fs.id', '=', 'd.formato_surtido_id');
        if ($f['reporte'] !== 'composiciones' || empty($f['historial'])) {
            $q->whereNull('d.retirado_en');
        }
        FiltrosIndicadores::pedido($q, $f);
        if ($periodo) {
            FiltrosIndicadores::periodo($q, 'pe.fecha_pedido', $f);
        }
        foreach (['producto_id' => DB::raw('COALESCE(p.producto_id, fs.producto_id)'), 'presentacion_id' => 'd.presentacion_producto_id', 'tamano' => 'd.unidades_por_empaque'] as $campo => $columna) {
            if (! empty($f[$campo])) {
                $q->where($columna, $f[$campo]);
            }
        }
        if (! empty($f['modalidad'])) {
            $f['modalidad'] === 'surtido' ? $q->where('d.tipo_linea', 'surtido') : $q->where('d.tipo_linea', 'normal')->where('d.modalidad', $f['modalidad']);
        }

        return $q;
    }

    public function consultaComercial(array $f): Builder
    {
        $paquetes = DB::table('pedido_paquetes_surtidos as ps')->join('paquete_surtido_composiciones as c', function ($j): void {
            $j->on('c.paquete_surtido_id', '=', 'ps.id')->on('c.version', '=', 'ps.version');
        })->where('ps.activo', true)->groupBy('ps.pedido_detalle_id')->select('ps.pedido_detalle_id')->selectRaw("SUM(CASE WHEN c.estado = 'definida' THEN 1 ELSE 0 END) definidos");
        $q = $this->lineas($f)->leftJoinSub($paquetes, 'pa', 'pa.pedido_detalle_id', '=', 'd.id')->select(['d.*', 'pe.estado', 'pe.fecha_pedido', 'pe.fecha_entrega', 'pe.modelo_comercial', 'pa.definidos']);
        if (! empty($f['composicion'])) {
            $q->where('d.tipo_linea', 'surtido')->whereRaw($f['composicion'] === 'definida' ? 'COALESCE(pa.definidos,0) = d.cantidad' : 'COALESCE(pa.definidos,0) < d.cantidad');
        }

        return $q;
    }

    public function comercial(array $f): \Generator
    {
        foreach ($this->consultaComercial($f)->lazyById(100, 'd.id', 'id') as $d) {
            yield ['pedido_id' => $d->pedido_id, 'linea_id' => $d->id, 'fecha_pedido' => $d->fecha_pedido, 'fecha_entrega' => $d->fecha_entrega, 'estado_actual' => $d->estado,
                'tipo_linea' => $d->tipo_linea, 'modalidad' => $d->modalidad, 'descripcion' => $d->descripcion_producto, 'sku' => $d->tipo_linea === 'normal' ? $d->codigo_sku : '',
                'cantidad_comercial' => (int) $d->cantidad, 'tamano' => $d->unidades_por_empaque, 'paquetes_definidos' => $d->tipo_linea === 'surtido' ? (int) $d->definidos : 0,
                'paquetes_pendientes' => $d->tipo_linea === 'surtido' ? (int) $d->cantidad - (int) $d->definidos : 0,
                'importe_centimos' => FiltrosIndicadores::centimos($d->importe), 'modelo_comercial' => $d->modelo_comercial ?? 'histórico', 'unidades_en_espera' => (int) $d->cantidad_espera, 'ajuste_fisico_explicito' => $d->cantidad_fisica_ajustada];
        }
    }

    public function composiciones(array $f): \Generator
    {
        $q = $this->lineas(array_diff_key($f, array_flip(['presentacion_id'])))
            ->join('pedido_paquetes_surtidos as ps', 'ps.pedido_detalle_id', '=', 'd.id')
            ->join('paquete_surtido_composiciones as c', 'c.paquete_surtido_id', '=', 'ps.id')
            ->leftJoin('paquete_surtido_componentes as co', 'co.composicion_id', '=', 'c.id')->where('d.tipo_linea', 'surtido');
        if (! $f['historial']) {
            $q->where('ps.activo', true)->whereColumn('ps.version', 'c.version');
        }
        foreach (['composicion' => 'c.estado', 'presentacion_id' => 'co.presentacion_producto_id', 'sku' => 'co.sku'] as $campo => $col) {
            if (! empty($f[$campo])) {
                $q->where($col, $f[$campo]);
            }
        }
        foreach ($q->orderBy('c.id')->orderBy('co.id')->select(['d.pedido_id', 'pe.estado as estado_pedido', 'd.id as linea_id', 'ps.id as paquete_id', 'ps.numero', 'ps.activo', 'ps.version as vigente', 'c.id as composicion_id', 'c.version', 'c.estado', 'c.created_at', 'co.id as componente_id', 'co.presentacion_producto_id', 'co.sku', 'co.descripcion', 'co.cantidad', 'd.unidades_por_empaque'])->lazy(100) as $r) {
            yield ['pedido_id' => $r->pedido_id, 'linea_id' => $r->linea_id, 'paquete_id' => $r->paquete_id, 'numero_paquete' => $r->numero, 'composicion_id' => $r->composicion_id,
                'version' => $r->version, 'version_vigente' => (int) $r->vigente === (int) $r->version && $r->activo ? 'Sí' : 'No', 'estado_composicion' => $r->estado,
                'fecha_registro' => $r->created_at, 'componente_id' => $r->componente_id, 'presentacion_id' => $r->presentacion_producto_id, 'sku' => $r->sku, 'descripcion' => $r->descripcion, 'unidades_fisicas' => $r->cantidad,
                'modelo_comercial' => 'histórico', 'capacidad_paquete' => $r->unidades_por_empaque,
                'demanda_definitiva' => (int) $r->vigente === (int) $r->version && $r->activo && $r->estado === 'definida' && in_array($r->estado_pedido, ['confirmado', 'en_preparacion'], true) ? 'Sí' : 'No'];
        }
        yield from $this->agrupacionesLogisticas($f);
    }

    private function agrupacionesLogisticas(array $f): \Generator
    {
        if ((! empty($f['modalidad']) && $f['modalidad'] !== 'unidad') || ($f['composicion'] ?? '') === 'pendiente') {
            return;
        }
        $ultimas = DB::table('pedido_versiones')->groupBy('pedido_id')->select('pedido_id')->selectRaw('MAX(numero) numero');
        $q = DB::table('pedido_agrupacion_detalles as co')->join('pedido_agrupaciones as g', 'g.id', '=', 'co.agrupacion_id')
            ->join('pedido_versiones as v', 'v.id', '=', 'g.pedido_version_id')->join('pedidos as pe', 'pe.id', '=', 'v.pedido_id')
            ->join('pedido_detalles as d', 'd.id', '=', 'co.pedido_detalle_id')->join('presentaciones_producto as p', 'p.id', '=', 'd.presentacion_producto_id')
            ->joinSub($ultimas, 'uv', 'uv.pedido_id', '=', 'v.pedido_id');
        if (! $f['historial']) {
            $q->whereColumn('v.numero', 'uv.numero');
        }
        FiltrosIndicadores::pedido($q, $f);
        FiltrosIndicadores::periodo($q, 'pe.fecha_pedido', $f);
        foreach (['producto_id' => 'p.producto_id', 'presentacion_id' => 'p.id', 'sku' => 'd.codigo_sku', 'tamano' => 'g.capacidad'] as $campo => $columna) {
            if (! empty($f[$campo])) {
                $q->where($columna, $f[$campo]);
            }
        }
        foreach ($q->select(['co.id', 'co.cantidad', 'd.id as linea_id', 'd.presentacion_producto_id', 'd.codigo_sku', 'd.descripcion_producto', 'v.pedido_id', 'v.id as version_id', 'v.numero as version', 'uv.numero as vigente', 'g.id as grupo_id', 'g.numero', 'g.capacidad', 'v.created_at'])->lazyById(100, 'co.id', 'id') as $r) {
            yield ['pedido_id' => $r->pedido_id, 'linea_id' => $r->linea_id, 'paquete_id' => $r->grupo_id, 'numero_paquete' => $r->numero,
                'composicion_id' => $r->version_id, 'version' => $r->version, 'version_vigente' => (int) $r->version === (int) $r->vigente ? 'Sí' : 'No',
                'estado_composicion' => 'definida', 'fecha_registro' => $r->created_at, 'componente_id' => $r->id, 'presentacion_id' => $r->presentacion_producto_id,
                'sku' => $r->codigo_sku, 'descripcion' => $r->descripcion_producto, 'unidades_fisicas' => (int) $r->cantidad,
                'demanda_definitiva' => 'No genera demanda adicional', 'modelo_comercial' => 'unidades', 'capacidad_paquete' => $r->capacidad];
        }
    }

    public function consultaFisica(array $f): Builder
    {
        $q = $this->lineas(array_diff_key($f, array_flip(['presentacion_id'])), false)->whereIn('pe.estado', ['confirmado', 'en_preparacion', 'despacho_parcial']);
        FiltrosIndicadores::periodo($q, 'pe.fecha_entrega', $f);
        $salidas = DB::table('pedido_despacho_lineas')->groupBy('detalle_comercial_id')->select('detalle_comercial_id')->selectRaw('SUM(cantidad) cantidad');
        $normales = (clone $q)->leftJoinSub($salidas, 'du', 'du.detalle_comercial_id', '=', 'd.id')->where('d.tipo_linea', 'normal')
            ->whereRaw("(pe.modelo_comercial IS NULL OR pe.modelo_comercial <> 'unidades' OR d.cantidad > COALESCE(du.cantidad,0))")
            ->selectRaw("d.pedido_id,d.id linea_id,NULL paquete_id,NULL composicion_id,NULL componente_id,d.presentacion_producto_id presentacion_id,d.codigo_sku sku,d.descripcion_producto descripcion,CASE WHEN pe.modelo_comercial='unidades' THEN d.cantidad-COALESCE(du.cantidad,0) WHEN d.cantidad_fisica_ajustada IS NOT NULL THEN d.cantidad_fisica_ajustada WHEN d.modalidad='unidad' THEN d.cantidad WHEN COALESCE(d.unidades_por_empaque,p.unidades_por_empaque)>0 THEN d.cantidad * COALESCE(d.unidades_por_empaque,p.unidades_por_empaque) ELSE NULL END unidades,CASE WHEN pe.modelo_comercial='unidades' THEN 'Unidades vigentes aún no despachadas' WHEN d.cantidad_fisica_ajustada IS NOT NULL THEN 'Ajuste físico revisado' WHEN d.modalidad='empaque' AND d.unidades_por_empaque IS NULL THEN 'Respaldo del catálogo' ELSE 'Tamaño de la línea' END fuente,CASE WHEN pe.modelo_comercial='unidades' THEN d.cantidad_espera ELSE 0 END espera");
        $surtidos = (clone $q)->join('pedido_paquetes_surtidos as ps', 'ps.pedido_detalle_id', '=', 'd.id')->join('paquete_surtido_composiciones as c', function ($j): void {
            $j->on('c.paquete_surtido_id', '=', 'ps.id')->on('c.version', '=', 'ps.version');
        })->join('paquete_surtido_componentes as co', 'co.composicion_id', '=', 'c.id')->where('ps.activo', true)->where('c.estado', 'definida')
            ->selectRaw("d.pedido_id,d.id linea_id,ps.id paquete_id,c.id composicion_id,co.id componente_id,co.presentacion_producto_id presentacion_id,co.sku,co.descripcion,co.cantidad unidades,'Composición vigente definida' fuente,0 espera");
        $fisicas = DB::query()->fromSub($normales->unionAll($surtidos), 'f');
        foreach (['presentacion_id', 'sku'] as $col) {
            if (! empty($f[$col])) {
                $fisicas->where($col, $f[$col]);
            }
        }

        return $fisicas;
    }

    public function fisicas(array $f): \Generator
    {
        foreach ($this->consultaFisica($f)->orderBy('pedido_id')->orderBy('linea_id')->orderBy('componente_id')->lazy(100) as $r) {
            yield ['pedido_id' => $r->pedido_id, 'linea_id' => $r->linea_id, 'paquete_id' => $r->paquete_id, 'composicion_id' => $r->composicion_id, 'componente_id' => $r->componente_id, 'presentacion_id' => $r->presentacion_id, 'sku' => $r->sku, 'descripcion' => $r->descripcion, 'unidades_fisicas' => $r->unidades === null ? null : (int) $r->unidades, 'fuente' => $r->fuente, 'unidades_en_espera' => (int) $r->espera];
        }
    }

    public function preparacion(array $f): \Generator
    {
        $codigo = 'COALESCE(actual.codigo_sku,f.sku)';
        $demanda = $this->consultaFisica($f)->leftJoin('presentaciones_producto as actual', 'actual.id', '=', 'f.presentacion_id')
            ->groupBy('f.presentacion_id', DB::raw($codigo))->select('f.presentacion_id')
            ->selectRaw($codigo.' sku, SUM(f.unidades) solicitadas, SUM(f.espera) espera, SUM(CASE WHEN f.unidades IS NULL THEN 1 ELSE 0 END) incompletas');
        $q = DB::query()->fromSub($demanda, 'f')->leftJoin('inventarios as i', 'i.presentacion_producto_id', '=', 'f.presentacion_id')->select(['f.*', 'i.cantidad_actual']);
        $pendientes = false;
        foreach ($this->pendientes(array_diff_key($f, array_flip(['sku', 'presentacion_id']))) as $ignorado) {
            $pendientes = true;
            break;
        }
        foreach ($q->orderBy('f.presentacion_id')->orderBy('f.sku')->lazy(100) as $r) {
            $n = (int) $r->incompletas > 0 ? null : (int) $r->solicitadas;
            $stock = (int) ($r->cantidad_actual ?? 0);
            $espera = (int) $r->espera;
            $activas = $n === null ? null : $n - $espera;
            yield ['presentacion_id' => $r->presentacion_id, 'sku' => $r->sku, 'solicitadas' => $n, 'stock_actual' => $stock, 'faltante_conocido' => $activas === null ? null : max(0, $activas - $stock), 'unidades_en_espera' => $espera, 'unidades_activas' => $activas,
                'cobertura' => $pendientes ? 'Hay composiciones pendientes; cobertura no definitiva' : ($n === null ? 'No disponible' : ($stock >= $activas ? 'Cubierta según stock' : 'Falta cubrir')).($espera > 0 ? ' · Atención pendiente por unidades en espera' : '')];
        }
    }

    public function pendientes(array $f): \Generator
    {
        $base = $this->lineas(array_diff_key($f, array_flip(['presentacion_id', 'sku', 'composicion'])), false)->whereIn('pe.estado', ['confirmado', 'en_preparacion'])->where('d.tipo_linea', 'surtido');
        FiltrosIndicadores::periodo($base, 'pe.fecha_entrega', $f);
        $porComposicion = DB::table('paquete_surtido_componentes')->select('composicion_id')->selectRaw('SUM(cantidad) declaradas')->groupBy('composicion_id');
        $paquetes = DB::table('pedido_paquetes_surtidos as ps')->join('paquete_surtido_composiciones as c', function ($j): void {
            $j->on('c.paquete_surtido_id', '=', 'ps.id')->on('c.version', '=', 'ps.version');
        })->leftJoinSub($porComposicion, 'pc', 'pc.composicion_id', '=', 'c.id')->where('ps.activo', true)->groupBy('ps.pedido_detalle_id')->select('ps.pedido_detalle_id')
            ->selectRaw("SUM(CASE WHEN c.estado='definida' THEN 1 ELSE 0 END) definidos, SUM(COALESCE(pc.declaradas,0)) declaradas");
        foreach ($base->leftJoinSub($paquetes, 'pa', 'pa.pedido_detalle_id', '=', 'd.id')->select(['d.*', 'pa.definidos', 'pa.declaradas'])->lazyById(100, 'd.id', 'id') as $d) {
            $pendientes = (int) $d->cantidad - (int) $d->definidos;
            if ($pendientes > 0) {
                yield ['pedido_id' => $d->pedido_id, 'linea_id' => $d->id, 'descripcion' => $d->descripcion_producto, 'paquetes_pendientes' => $pendientes, 'unidades_sin_composicion' => $pendientes * (int) $d->unidades_por_empaque, 'unidades_sin_distribuir' => (int) $d->cantidad * (int) $d->unidades_por_empaque - (int) $d->declaradas];
            }
        }
    }
}
