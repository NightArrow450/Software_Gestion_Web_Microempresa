<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEnvasado;
use App\Models\Envasado;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Services\TransicionesPedido;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CumplimientoPedidoController extends Controller
{
    public function index(Request $request): View
    {
        $estados = ['confirmado', 'en_preparacion', 'listo_despacho', 'despachado', 'entregado'];
        $estado = (string) $request->query('estado', '');
        if (! in_array($estado, $estados, true)) {
            $estado = '';
        }

        $pedidos = Pedido::query()->with('cliente')
            ->whereIn('estado', $estados)
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->orderBy('fecha_entrega')
            ->orderBy('id')
            ->paginate(15)->withQueryString();

        return view('cumplimiento.index', compact('pedidos', 'estado', 'estados'));
    }

    /** Consolida productos pendientes sin afectar existencias. */
    public function listaPreparacion(Request $request): View
    {
        $filtros = $request->validate([
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('fecha_desde') ? ['after_or_equal:fecha_desde'] : [])],
            'produccion_desde' => ['nullable', 'date_format:Y-m-d'],
            'produccion_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:produccion_desde'],
        ]);
        $fechaDesde = $filtros['fecha_desde'] ?? null;
        $fechaHasta = array_key_exists('fecha_hasta', $filtros) ? $filtros['fecha_hasta'] : now('America/Lima')->toDateString();
        $produccionDesde = $filtros['produccion_desde'] ?? now('America/Lima')->toDateString();
        $produccionHasta = $filtros['produccion_hasta'] ?? now('America/Lima')->toDateString();
        if ($produccionHasta < $produccionDesde) {
            throw ValidationException::withMessages(['produccion_hasta' => 'El fin del período de producción debe ser igual o posterior al inicio.']);
        }

        $pedidos = Pedido::query()
            ->with(['detalles.presentacion.producto', 'detalles.presentacion.variante'])
            ->whereIn('estado', ['confirmado', 'en_preparacion'])
            ->when($fechaDesde !== null, fn ($q) => $q->whereDate('fecha_entrega', '>=', $fechaDesde))
            ->when($fechaHasta !== null, fn ($q) => $q->whereDate('fecha_entrega', '<=', $fechaHasta))
            ->orderBy('fecha_entrega')
            ->orderBy('id')
            ->get();

        $agrupados = [];
        foreach ($pedidos as $pedido) {
            foreach ($pedido->detalles as $detalle) {
                $presentacion = $detalle->presentacion;
                $cantidad = (int) $detalle->cantidad;
                $porEmpaque = (int) $detalle->unidadesPorEmpaqueEfectivas();
                $esEmpaque = $detalle->modalidad === 'empaque';
                $totalUnidades = $esEmpaque
                    ? ($porEmpaque > 0 ? $cantidad * $porEmpaque : null)
                    : $cantidad;

                $clave = $presentacion
                    ? json_encode([
                        $presentacion->producto_id, $presentacion->variante_producto_id,
                        $presentacion->tipo_envase, $presentacion->contenido, $presentacion->unidad_medida,
                    ], JSON_THROW_ON_ERROR)
                    : 'historico:'.$detalle->codigo_sku;

                if (! isset($agrupados[$clave])) {
                    $agrupados[$clave] = [
                        'producto' => $presentacion?->producto?->nombre ?? $detalle->descripcion_producto,
                        'producto_clave' => $presentacion ? 'producto:'.$presentacion->producto_id : 'historico:'.$detalle->descripcion_producto,
                        'dimension' => match ($presentacion?->unidad_medida) {
                            'L', 'ml' => 0, 'kg', 'g' => 1, default => 2
                        },
                        'capacidad' => $presentacion ? (int) round((float) $presentacion->contenido * 100) * (in_array($presentacion->unidad_medida, ['L', 'kg'], true) ? 1000 : 1) : PHP_INT_MAX,
                        'presentaciones' => [],
                        'skus' => [],
                        'variante' => $presentacion?->variante?->valor,
                        'envase' => $presentacion?->tipo_envase,
                        'presentacion' => $presentacion
                            ? rtrim(rtrim(number_format((float) $presentacion->contenido, 2, '.', ''), '0'), '.').' '.$presentacion->unidad_medida
                            : 'Presentación histórica',
                        'desglose' => [],
                        'total_unidades' => 0,
                    ];
                }

                $agrupados[$clave]['skus'][$detalle->codigo_sku] = $detalle->codigo_sku;
                if ($presentacion) {
                    $id = $presentacion->id;
                    if (! isset($agrupados[$clave]['presentaciones'][$id])) {
                        $agrupados[$clave]['presentaciones'][$id] = ['id' => $id, 'sku' => $presentacion->codigo_sku, 'solicitadas' => 0, 'activa' => $presentacion->estado && $presentacion->producto->estado && ($presentacion->variante?->estado ?? true)];
                    }
                    $anterior = $agrupados[$clave]['presentaciones'][$id]['solicitadas'];
                    $agrupados[$clave]['presentaciones'][$id]['solicitadas'] = $anterior === null || $totalUnidades === null ? null : $anterior + $totalUnidades;
                }
                $claveDesglose = $detalle->modalidad.'|'.$porEmpaque;
                if (! isset($agrupados[$clave]['desglose'][$claveDesglose])) {
                    $agrupados[$clave]['desglose'][$claveDesglose] = [
                        'modalidad' => $detalle->modalidad,
                        'por_empaque' => $esEmpaque && $porEmpaque > 0 ? $porEmpaque : null,
                        'cantidad' => 0,
                        'total_unidades' => 0,
                    ];
                }
                $agrupados[$clave]['desglose'][$claveDesglose]['cantidad'] += $cantidad;
                $agrupados[$clave]['desglose'][$claveDesglose]['total_unidades'] = $totalUnidades === null
                    ? null
                    : $agrupados[$clave]['desglose'][$claveDesglose]['total_unidades'] + $totalUnidades;
                // Si una cantidad no puede calcularse, nunca se presenta un total inventado.
                $agrupados[$clave]['total_unidades'] =
                    $agrupados[$clave]['total_unidades'] === null || $totalUnidades === null
                        ? null
                        : $agrupados[$clave]['total_unidades'] + $totalUnidades;
            }
        }

        $ids = collect($agrupados)->flatMap(fn (array $fila): array => array_keys($fila['presentaciones']))->unique()->values();
        $stock = Inventario::whereIn('presentacion_producto_id', $ids)->pluck('cantidad_actual', 'presentacion_producto_id');
        $produccion = Envasado::whereIn('presentacion_producto_id', $ids)
            ->where('created_at', '>=', $produccionDesde.' 00:00:00')
            ->where('created_at', '<', CarbonImmutable::parse($produccionHasta, 'America/Lima')->addDay()->startOfDay())
            ->get(['presentacion_producto_id', 'pedido_id', 'cantidad', 'unidades_revertidas']);
        $porPresentacion = $produccion->groupBy('presentacion_producto_id');
        $pedidosIds = $pedidos->modelKeys();
        $filas = collect(array_values($agrupados))->map(function (array $fila) use ($stock, $porPresentacion, $pedidosIds): array {
            foreach ($fila['presentaciones'] as &$presentacion) {
                $registros = $porPresentacion->get($presentacion['id'], collect());
                $presentacion['stock'] = (int) ($stock[$presentacion['id']] ?? 0);
                $presentacion['envasadas'] = $registros->sum(fn (Envasado $registro): int => $registro->cantidad - $registro->unidades_revertidas);
                $presentacion['vinculadas'] = $registros->whereIn('pedido_id', $pedidosIds)->sum(fn (Envasado $registro): int => $registro->cantidad - $registro->unidades_revertidas);
                $presentacion['faltante'] = $presentacion['solicitadas'] === null ? null : max(0, $presentacion['solicitadas'] - $presentacion['stock']);
            }
            unset($presentacion);
            $fila['stock_disponible'] = array_sum(array_column($fila['presentaciones'], 'stock'));
            $fila['envasadas'] = array_sum(array_column($fila['presentaciones'], 'envasadas'));
            $fila['vinculadas'] = array_sum(array_column($fila['presentaciones'], 'vinculadas'));
            $fila['faltante'] = $fila['total_unidades'] === null || $fila['presentaciones'] === [] ? null : array_sum(array_column($fila['presentaciones'], 'faltante'));
            $fila['cobertura'] = $fila['faltante'] === null ? 'No disponible' : ($fila['faltante'] === 0 ? 'Cubierta según stock' : ($fila['faltante'] < $fila['total_unidades'] ? 'Parcialmente cubierta' : 'Pendiente'));

            return $fila;
        })->groupBy('producto_clave')->flatMap(fn ($grupo) => $grupo->sortBy([['dimension', 'asc'], ['capacidad', 'asc']]))->values();

        $permisos = $request->user()->loadMissing('role.permisos')->role?->permisos;
        $puedeRegistrar = $permisos?->contains('nombre', 'envasado.ver') && $permisos?->contains('nombre', 'envasado.registrar');
        $configuraciones = $puedeRegistrar ? ConfiguracionEnvasado::whereIn('presentacion_producto_id', $ids)->where('estado', true)->orderBy('nombre')->get() : collect();

        return view('cumplimiento.lista-preparacion', compact('filas', 'fechaDesde', 'fechaHasta', 'produccionDesde', 'produccionHasta', 'puedeRegistrar', 'configuraciones', 'pedidos'));
    }

    public function show(Pedido $pedido): View
    {
        $pedido->load([
            'detalles.presentacion', 'cliente', 'vendedor',
            'historialEstados' => fn ($q) => $q->with('usuario')->orderBy('id'),
        ]);

        return view('cumplimiento.show', compact('pedido'));
    }

    public function iniciarPreparacion(
        Request $request,
        Pedido $pedido,
        TransicionesPedido $transiciones
    ): RedirectResponse {
        $transiciones->registrar(
            $pedido, 'confirmado', 'en_preparacion', $request->user()->id,
            ['preparacion_iniciada_por_id' => $request->user()->id,
                'preparacion_iniciada_en' => now()],
            'Preparación iniciada.'
        );

        return redirect()->route('cumplimiento.show', $pedido)
            ->with('success', 'Se inició la preparación del pedido.');
    }

    public function finalizarPreparacion(
        Request $request,
        Pedido $pedido,
        TransicionesPedido $transiciones
    ): RedirectResponse {
        $transiciones->registrar(
            $pedido, 'en_preparacion', 'listo_despacho', $request->user()->id,
            ['preparacion_finalizada_por_id' => $request->user()->id,
                'preparacion_finalizada_en' => now()],
            'Preparación finalizada. Pedido listo para despacho.'
        );

        return redirect()->route('cumplimiento.show', $pedido)
            ->with('success', 'Pedido listo para despacho.');
    }

    public function despachar(
        Request $request,
        Pedido $pedido,
        TransicionesPedido $transiciones
    ): RedirectResponse {
        $datos = $request->validate([
            'observacion_despacho' => ['nullable', 'string', 'max:1000'],
        ]);

        $observacion = trim((string) ($datos['observacion_despacho'] ?? ''));
        $transiciones->registrar(
            $pedido, 'listo_despacho', 'despachado', $request->user()->id,
            ['despachado_por_id' => $request->user()->id,
                'despachado_en' => now(),
                'observacion_despacho' => $observacion !== '' ? $observacion : null],
            $observacion !== '' ? $observacion : 'Pedido despachado.'
        );

        return redirect()->route('cumplimiento.show', $pedido)
            ->with('success', 'Se registró el despacho del pedido.');
    }

    public function entregar(
        Request $request,
        Pedido $pedido,
        TransicionesPedido $transiciones
    ): RedirectResponse {
        $datos = $request->validate([
            'observacion_entrega' => ['nullable', 'string', 'max:1000'],
        ]);

        $observacion = trim((string) ($datos['observacion_entrega'] ?? ''));
        $transiciones->registrar(
            $pedido, 'despachado', 'entregado', $request->user()->id,
            ['entregado_por_id' => $request->user()->id,
                'entregado_en' => now(),
                'observacion_entrega' => $observacion !== '' ? $observacion : null],
            $observacion !== '' ? $observacion : 'Entrega confirmada.'
        );

        return redirect()->route('cumplimiento.show', $pedido)
            ->with('success', 'Entrega confirmada correctamente.');
    }
}
