<?php

namespace App\Services;

use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistribuirDespachoUnidades
{
    public function anteriores(int $pedidoId): array
    {
        $paquetes = [];
        $sueltas = [];
        foreach (DB::table('pedido_despachos')->where('pedido_id', $pedidoId)->orderBy('id')->lazyById(100) as $salida) {
            $datos = json_decode($salida->distribucion, true, 512, JSON_THROW_ON_ERROR);
            foreach ($datos['paquetes'] as $paquete) {
                $paquetes[$paquete['identidad']] = $paquete;
            }
            foreach ($datos['sueltas'] as $sku => $cantidad) {
                $sueltas[$sku] = ($sueltas[$sku] ?? 0) + $cantidad;
            }
        }

        return ['paquetes' => $paquetes, 'sueltas' => $sueltas];
    }

    public function comprobarEdicion(int $pedidoId, array $grupos, array $detalles): void
    {
        $anteriores = $this->anteriores($pedidoId);
        $vigentes = collect($grupos)->keyBy('identidad');
        foreach ($anteriores['paquetes'] as $identidad => $anterior) {
            $actual = $vigentes->get($identidad);
            if ($actual === null || $this->normalizar($anterior) !== $this->normalizar($actual)) {
                throw ValidationException::withMessages(['agrupaciones' => 'Un paquete ya despachado conserva su composición y capacidad. Modifica únicamente las agrupaciones que aún no salieron.']);
            }
        }
        $agrupadas = $this->sumar($grupos);
        foreach ($detalles as $detalle) {
            if ((int) $detalle->cantidad - ($agrupadas[$detalle->presentacion_producto_id] ?? 0) < ($anteriores['sueltas'][$detalle->presentacion_producto_id] ?? 0)) {
                throw ValidationException::withMessages(['agrupaciones' => 'Las unidades ya despachadas sueltas no pueden volver a asignarse a paquetes.']);
            }
        }
    }

    public function preparar(Pedido $pedido, array $seleccionados, array $cantidades): array
    {
        $foto = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]))[$pedido->id] ?? [];
        $grupos = collect($foto['agrupaciones'] ?? [])->keyBy('identidad');
        $anteriores = $this->anteriores($pedido->id);
        $paquetes = [];
        foreach ($seleccionados as $identidad) {
            if (! $grupos->has($identidad) || isset($anteriores['paquetes'][$identidad])) {
                throw ValidationException::withMessages(['paquetes' => 'El paquete no pertenece a la distribución vigente o ya fue despachado.']);
            }
            $paquetes[] = $grupos->get($identidad);
        }
        $enPaquetes = $this->sumar($paquetes);
        $agrupadas = $this->sumar($grupos->values()->all());
        $vigentes = $pedido->detalles()->get()->keyBy('presentacion_producto_id');
        $sueltas = [];
        foreach (array_unique(array_merge(array_keys($cantidades), array_keys($enPaquetes))) as $sku) {
            $salen = (int) ($cantidades[$sku] ?? 0);
            $enGrupo = (int) ($enPaquetes[$sku] ?? 0);
            $libres = (int) ($vigentes->get($sku)->cantidad ?? 0) - ($agrupadas[$sku] ?? 0) - ($anteriores['sueltas'][$sku] ?? 0);
            if ($salen < $enGrupo || $salen - $enGrupo > $libres) {
                throw ValidationException::withMessages(['paquetes' => 'La salida no coincide con los paquetes seleccionados y las unidades sueltas pendientes. Si necesitas abrir o cambiar un paquete, ajusta antes la distribución del pedido con un motivo.']);
            }
            if ($salen > $enGrupo) {
                $sueltas[$sku] = $salen - $enGrupo;
            }
        }

        return ['paquetes' => $paquetes, 'sueltas' => $sueltas];
    }

    private function sumar(array $grupos): array
    {
        $suma = [];
        foreach ($grupos as $grupo) {
            foreach ($grupo['componentes'] as $componente) {
                $sku = $componente['sku_id'];
                $suma[$sku] = ($suma[$sku] ?? 0) + $componente['cantidad'];
            }
        }

        return $suma;
    }

    private function normalizar(array $grupo): array
    {
        return ['capacidad' => (int) $grupo['capacidad'], 'componentes' => collect($grupo['componentes'])
            ->map(fn (array $c): array => ['sku_id' => (int) $c['sku_id'], 'cantidad' => (int) $c['cantidad']])->sortBy('sku_id')->values()->all()];
    }
}
