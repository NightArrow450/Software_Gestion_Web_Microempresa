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
        $q = $this->lineas($f)->leftJoinSub($paquetes, 'pa', 'pa.pedido_detalle_id', '=', 'd.id')->select(['d.*', 'pe.estado', 'pe.fecha_pedido', 'pe.fecha_entrega', 'pa.definidos']);
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
                'importe_centimos' => FiltrosIndicadores::centimos($d->importe)];
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
        foreach ($q->orderBy('c.id')->orderBy('co.id')->select(['d.pedido_id', 'd.id as linea_id', 'ps.id as paquete_id', 'ps.numero', 'ps.activo', 'ps.version as vigente', 'c.id as composicion_id', 'c.version', 'c.estado', 'c.created_at', 'co.id as componente_id', 'co.presentacion_producto_id', 'co.sku', 'co.descripcion', 'co.cantidad'])->lazy(100) as $r) {
            yield ['pedido_id' => $r->pedido_id, 'linea_id' => $r->linea_id, 'paquete_id' => $r->paquete_id, 'numero_paquete' => $r->numero, 'composicion_id' => $r->composicion_id,
                'version' => $r->version, 'version_vigente' => (int) $r->vigente === (int) $r->version && $r->activo ? 'Sí' : 'No', 'estado_composicion' => $r->estado,
                'fecha_registro' => $r->created_at, 'componente_id' => $r->componente_id, 'presentacion_id' => $r->presentacion_producto_id, 'sku' => $r->sku, 'descripcion' => $r->descripcion, 'unidades_fisicas' => $r->cantidad,
                'demanda_definitiva' => (int) $r->vigente === (int) $r->version && $r->activo && $r->estado === 'definida' ? 'Sí' : 'No'];
        }
    }

    public function consultaFisica(array $f): Builder
    {
        $q = $this->lineas(array_diff_key($f, array_flip(['presentacion_id'])), false)->whereIn('pe.estado', ['confirmado', 'en_preparacion']);
        FiltrosIndicadores::periodo($q, 'pe.fecha_entrega', $f);
        $normales = (clone $q)->where('d.tipo_linea', 'normal')->selectRaw("d.pedido_id,d.id linea_id,NULL paquete_id,NULL composicion_id,NULL componente_id,d.presentacion_producto_id presentacion_id,d.codigo_sku sku,d.descripcion_producto descripcion,CASE WHEN d.modalidad='unidad' THEN d.cantidad WHEN COALESCE(d.unidades_por_empaque,p.unidades_por_empaque)>0 THEN d.cantidad * COALESCE(d.unidades_por_empaque,p.unidades_por_empaque) ELSE NULL END unidades,CASE WHEN d.modalidad='empaque' AND d.unidades_por_empaque IS NULL THEN 'Respaldo del catálogo' ELSE 'Tamaño de la línea' END fuente");
        $surtidos = (clone $q)->join('pedido_paquetes_surtidos as ps', 'ps.pedido_detalle_id', '=', 'd.id')->join('paquete_surtido_composiciones as c', function ($j): void {
            $j->on('c.paquete_surtido_id', '=', 'ps.id')->on('c.version', '=', 'ps.version');
        })->join('paquete_surtido_componentes as co', 'co.composicion_id', '=', 'c.id')->where('ps.activo', true)->where('c.estado', 'definida')
            ->selectRaw("d.pedido_id,d.id linea_id,ps.id paquete_id,c.id composicion_id,co.id componente_id,co.presentacion_producto_id presentacion_id,co.sku,co.descripcion,co.cantidad unidades,'Composición vigente definida' fuente");
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
            yield ['pedido_id' => $r->pedido_id, 'linea_id' => $r->linea_id, 'paquete_id' => $r->paquete_id, 'composicion_id' => $r->composicion_id, 'componente_id' => $r->componente_id, 'presentacion_id' => $r->presentacion_id, 'sku' => $r->sku, 'descripcion' => $r->descripcion, 'unidades_fisicas' => $r->unidades === null ? null : (int) $r->unidades, 'fuente' => $r->fuente];
        }
    }

    public function preparacion(array $f): \Generator
    {
        $codigo = 'COALESCE(actual.codigo_sku,f.sku)';
        $demanda = $this->consultaFisica($f)->leftJoin('presentaciones_producto as actual', 'actual.id', '=', 'f.presentacion_id')
            ->groupBy('f.presentacion_id', DB::raw($codigo))->select('f.presentacion_id')
            ->selectRaw($codigo.' sku, SUM(f.unidades) solicitadas, SUM(CASE WHEN f.unidades IS NULL THEN 1 ELSE 0 END) incompletas');
        $q = DB::query()->fromSub($demanda, 'f')->leftJoin('inventarios as i', 'i.presentacion_producto_id', '=', 'f.presentacion_id')->select(['f.*', 'i.cantidad_actual']);
        $pendientes = false;
        foreach ($this->pendientes(array_diff_key($f, array_flip(['sku', 'presentacion_id']))) as $ignorado) {
            $pendientes = true;
            break;
        }
        foreach ($q->orderBy('f.presentacion_id')->orderBy('f.sku')->lazy(100) as $r) {
            $n = (int) $r->incompletas > 0 ? null : (int) $r->solicitadas;
            $stock = (int) ($r->cantidad_actual ?? 0);
            yield ['presentacion_id' => $r->presentacion_id, 'sku' => $r->sku, 'solicitadas' => $n, 'stock_actual' => $stock, 'faltante_conocido' => $n === null ? null : max(0, $n - $stock),
                'cobertura' => $pendientes ? 'Hay composiciones pendientes; cobertura no definitiva' : ($n === null ? 'No disponible' : ($stock >= $n ? 'Cubierta según stock' : 'Falta cubrir'))];
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
