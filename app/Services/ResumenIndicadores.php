<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ResumenIndicadores
{
    public function __construct(private readonly FilasIndicadores $fuente, private readonly PedidosIndicadores $pedidos) {}

    public function pagina(array $f): array
    {
        $filas = [];
        $cantidad = 0;
        $totales = [];
        $noDisponibles = [];
        $importesComerciales = ['importe_activo_centimos' => 0, 'importe_cancelado_centimos' => 0];
        $sumables = match ($f['reporte']) {
            'situacion_entregas' => ['despachadas', 'aceptadas', 'rechazadas', 'pendientes', 'recibidas', 'aptas', 'danadas', 'por_revisar', 'no_recuperadas', 'reincorporadas', 'por_retornar', 'por_aprobar', 'aceptado_centimos', 'rechazado_centimos', 'pendiente_centimos'],
            'comercial' => ['importe_centimos'],
            'preparacion' => ['solicitadas', 'faltante_conocido'],
            'fisicas' => ['unidades_fisicas'],
            'pendientes' => ['unidades_en_espera'],
            'entregas' => ['aceptadas', 'rechazadas', 'aceptado_centimos', 'rechazado_centimos'],
            'retornos' => ['recibidas', 'aptas', 'danadas', 'en_revision', 'no_recuperadas'],
            'aprobaciones' => ['reincorporadas'],
            'envasados' => ['envasadas_brutas', 'correcciones_administrativas', 'perdidas_fisicas', 'efecto_neto', 'neto_actual_envasado'],
            default => [],
        };
        foreach ($this->fuente->filas($f) as $fila) {
            if ($f['reporte'] === 'comercial' && $f['economico']) {
                $importesComerciales[$fila['estado_actual'] === 'cancelado' ? 'importe_cancelado_centimos' : 'importe_activo_centimos'] += $fila['importe_centimos'];
            }
            if ($cantidad >= ($f['pagina'] - 1) * 25 && count($filas) < 25) {
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
            if ($f['economico']) {
                $comercial += $importesComerciales;
            }
            $cantidades = $this->pedidos->consultaComercial($f)->where('pe.estado', '<>', 'cancelado')->select([])->selectRaw("COALESCE(SUM(CASE WHEN d.presentacion_producto_id IS NOT NULL AND d.modalidad='unidad' THEN d.cantidad ELSE 0 END),0) unidades_comerciales, COALESCE(SUM(CASE WHEN d.presentacion_producto_id IS NULL THEN 1 ELSE 0 END),0) lineas_sin_sku_verificable")->first();
            $comercial += (array) $cantidades;
        }

        return compact('filas', 'cantidad', 'totales', 'noDisponibles', 'comercial');
    }
}
