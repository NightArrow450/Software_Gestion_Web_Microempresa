<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ResumenIndicadores
{
    private const TAMANO_PAGINA = 25;

    public function __construct(private readonly FilasIndicadores $fuente, private readonly PedidosIndicadores $pedidos) {}

    public function pagina(array $f): array
    {
        if ($f['reporte'] === 'comercial' && ! $f['economico']) {
            return $this->comercialSinImportes($f);
        }

        $filas = [];
        $cantidad = 0;
        $totales = [];
        $noDisponibles = [];
        $importesComerciales = ['importe_activo_centimos' => 0, 'importe_cancelado_centimos' => 0];
        $sumables = match ($f['reporte']) {
            'situacion_entregas' => ['despachadas', 'aceptadas', 'rechazadas', 'pendientes', 'recibidas', 'aptas', 'danadas', 'por_revisar', 'no_recuperadas', 'reincorporadas', 'por_retornar', 'por_aprobar', 'aceptado_centimos', 'rechazado_centimos', 'pendiente_centimos'],
            'comercial' => ['importe_centimos', 'paquetes_definidos', 'paquetes_pendientes'],
            'preparacion' => ['solicitadas', 'faltante_conocido'],
            'fisicas' => ['unidades_fisicas'],
            'pendientes' => ['paquetes_pendientes', 'unidades_sin_composicion', 'unidades_sin_distribuir'],
            'entregas' => ['aceptadas', 'rechazadas', 'aceptado_centimos', 'rechazado_centimos'],
            'retornos' => ['recibidas', 'aptas', 'danadas', 'en_revision', 'no_recuperadas'],
            'aprobaciones' => ['reincorporadas'],
            'envasados' => ['envasadas_brutas', 'correcciones_administrativas', 'perdidas_fisicas', 'efecto_neto', 'neto_actual_envasado'],
            default => [],
        };
        foreach ($this->fuente->filas($f) as $fila) {
            if ($f['reporte'] === 'comercial') {
                $importesComerciales[$fila['estado_actual'] === 'cancelado' ? 'importe_cancelado_centimos' : 'importe_activo_centimos'] += $fila['importe_centimos'];
            }
            if ($cantidad >= ($f['pagina'] - 1) * self::TAMANO_PAGINA && count($filas) < self::TAMANO_PAGINA) {
                $filas[] = $fila;
            }
            $cantidad++;
            foreach ($sumables as $campo) {
                if (! array_key_exists($campo, $fila)) {
                    continue;
                }
                if ($fila[$campo] === null) {
                    $noDisponibles[$campo] = ($noDisponibles[$campo] ?? 0) + 1;
                } else {
                    $totales[$campo] = ($totales[$campo] ?? 0) + (int) $fila[$campo];
                }
            }
        }
        foreach ($noDisponibles as $campo => $ignorados) {
            if (! array_key_exists($campo, $totales)) {
                $totales[$campo] = null;
            }
        }
        $comercial = [];
        if ($f['reporte'] === 'comercial') {
            $lineas = $this->pedidos->consultaComercial($f)->select('pe.id', 'pe.estado')->distinct();
            $q = DB::query()->fromSub($lineas, 'p');
            $comercial = (array) $q->selectRaw("COUNT(*) pedidos_registrados, COALESCE(SUM(CASE WHEN estado='cancelado' THEN 1 ELSE 0 END),0) pedidos_cancelados, COALESCE(SUM(CASE WHEN estado<>'cancelado' THEN 1 ELSE 0 END),0) pedidos_activos")->first();
            $comercial += $importesComerciales;
            $cantidades = $this->pedidos->consultaComercial($f)->where('pe.estado', '<>', 'cancelado')->select([])->selectRaw("COALESCE(SUM(CASE WHEN d.tipo_linea='normal' AND d.modalidad='unidad' THEN d.cantidad ELSE 0 END),0) unidades_comerciales, COALESCE(SUM(CASE WHEN d.tipo_linea='normal' AND d.modalidad='empaque' THEN d.cantidad ELSE 0 END),0) paquetes_normales, COALESCE(SUM(CASE WHEN d.tipo_linea='surtido' THEN d.cantidad ELSE 0 END),0) paquetes_surtidos")->first();
            $comercial += (array) $cantidades;
        }

        return compact('filas', 'cantidad', 'totales', 'noDisponibles', 'comercial');
    }

    private function comercialSinImportes(array $f): array
    {
        $porPedido = $this->pedidos->consultaComercial($f)
            ->select('pe.id', 'pe.estado')
            ->groupBy('pe.id', 'pe.estado')
            ->selectRaw("COUNT(*) AS lineas,
                SUM(CASE WHEN d.tipo_linea = 'surtido' THEN COALESCE(pa.definidos, 0) ELSE 0 END) AS definidos,
                SUM(CASE WHEN d.tipo_linea = 'surtido' THEN d.cantidad - COALESCE(pa.definidos, 0) ELSE 0 END) AS pendientes,
                SUM(CASE WHEN pe.estado <> 'cancelado' AND d.tipo_linea = 'normal' AND d.modalidad = 'unidad' THEN d.cantidad ELSE 0 END) AS unidades,
                SUM(CASE WHEN pe.estado <> 'cancelado' AND d.tipo_linea = 'normal' AND d.modalidad = 'empaque' THEN d.cantidad ELSE 0 END) AS normales,
                SUM(CASE WHEN pe.estado <> 'cancelado' AND d.tipo_linea = 'surtido' THEN d.cantidad ELSE 0 END) AS surtidos");

        $comercial = (array) DB::query()->fromSub($porPedido, 'p')->selectRaw("COUNT(*) AS pedidos_registrados,
            COALESCE(SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END), 0) AS pedidos_cancelados,
            COALESCE(SUM(CASE WHEN estado <> 'cancelado' THEN 1 ELSE 0 END), 0) AS pedidos_activos,
            COALESCE(SUM(unidades), 0) AS unidades_comerciales,
            COALESCE(SUM(normales), 0) AS paquetes_normales,
            COALESCE(SUM(surtidos), 0) AS paquetes_surtidos,
            COALESCE(SUM(lineas), 0) AS cantidad,
            COALESCE(SUM(definidos), 0) AS paquetes_definidos,
            COALESCE(SUM(pendientes), 0) AS paquetes_pendientes")->first();

        $cantidad = (int) $comercial['cantidad'];
        $totales = $cantidad === 0 ? [] : [
            'paquetes_definidos' => (int) $comercial['paquetes_definidos'],
            'paquetes_pendientes' => (int) $comercial['paquetes_pendientes'],
        ];
        unset($comercial['cantidad'], $comercial['paquetes_definidos'], $comercial['paquetes_pendientes']);

        $filas = [];
        $inicio = ($f['pagina'] - 1) * self::TAMANO_PAGINA;
        if ($inicio < $cantidad) {
            $campos = array_flip($this->fuente->campos($f));
            foreach ($this->pedidos->comercial($f, self::TAMANO_PAGINA, $inicio) as $fila) {
                $filas[] = array_intersect_key($fila, $campos);
            }
        }

        return ['filas' => $filas, 'cantidad' => $cantidad, 'totales' => $totales, 'noDisponibles' => [], 'comercial' => $comercial];
    }
}
