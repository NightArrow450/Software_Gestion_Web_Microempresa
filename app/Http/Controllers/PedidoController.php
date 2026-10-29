<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Services\AjustarPedidoHistorico;
use App\Services\GestionarPedidoUnidades;
use App\Services\GestionarSurtidos;
use App\Services\TransicionesPedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PedidoController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('search', ''));

        $pedidos = Pedido::with(['cliente', 'vendedor'])
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where(
                        'cliente_nombre',
                        'like',
                        "%{$busqueda}%"
                    )->orWhereHas(
                        'cliente',
                        fn ($cliente) => $cliente->where(
                            'telefono',
                            'like',
                            "%{$busqueda}%"
                        )
                    );

                    if (ctype_digit($busqueda)) {
                        $q->orWhere('id', (int) $busqueda);
                    }
                });
            })
            ->when(
                $request->filled('estado'),
                fn ($q) => $q->where(
                    'estado',
                    $request->query('estado')
                )
            )
            ->when(
                $request->filled('fecha_entrega'),
                fn ($q) => $q->whereDate(
                    'fecha_entrega',
                    $request->query('fecha_entrega')
                )
            )
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $opcionesImpresion = Pedido::orderByDesc('id')
            ->limit(200)
            ->get(['id', 'cliente_nombre']);

        return view(
            'orders.index',
            compact('pedidos', 'opcionesImpresion')
        );
    }

    public function create(): View
    {
        return app(PedidoUnidadesController::class)->formulario();
    }

    public function store(Request $request): RedirectResponse
    {
        return app(PedidoUnidadesController::class)->guardar($request);
    }

    public function show(Pedido $pedido): View
    {
        $pedido->load([
            'cliente',
            'vendedor',
            'procesadoPor',
            'detalles.presentacion',
            'historialEstados' => fn ($q) => $q
                ->with('usuario')
                ->orderBy('id'),
        ]);

        $versionesPedido = app(GestionarPedidoUnidades::class)->historial($pedido);
        $cambiosOriginales = app(GestionarPedidoUnidades::class)->cambiosOriginales($pedido);
        $fotosUnidades = app(GestionarPedidoUnidades::class)->fotografiasActuales(collect([$pedido]));

        return view('orders.show', compact('pedido', 'versionesPedido', 'fotosUnidades', 'cambiosOriginales'));
    }

    public function edit(Pedido $pedido): View
    {
        if ($pedido->modelo_comercial === 'unidades') {
            return app(PedidoUnidadesController::class)->formulario($pedido);
        }
        $pedido->load('detalles.presentacion');

        return view(
            'orders.edit',
            array_merge(
                $this->opcionesFormulario(),
                compact('pedido')
            )
        );
    }

    public function update(
        Request $request,
        Pedido $pedido
    ): RedirectResponse {
        if ($pedido->modelo_comercial === 'unidades') {
            return app(PedidoUnidadesController::class)->guardar($request, $pedido);
        }
        app(AjustarPedidoHistorico::class)->guardar($pedido, $request->all(), $request->user());

        return redirect()
            ->route('pedidos.show', $pedido)
            ->with(
                'success',
                'Se actualizó el pedido sin modificar su número.'
            );
    }

    public function procesar(
        Request $request,
        Pedido $pedido
    ): RedirectResponse {
        $data = $request->validate([
            'accion' => [
                'required',
                Rule::in(['confirmar', 'observar']),
            ],
            'motivo' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:accion,observar',
                Rule::when($request->input('accion') === 'observar', 'min:10'),
            ],
        ]);

        $nuevoEstado = $data['accion'] === 'confirmar'
            ? 'confirmado'
            : 'observado';

        $this->actualizarEstado(
            $pedido,
            $nuevoEstado,
            $request,
            $data['motivo'] ?? null
        );

        return back()->with(
            'success',
            'El pedido pasó a estado '.ucfirst($nuevoEstado).'.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HU-03: Cancelación de pedidos
    |--------------------------------------------------------------------------
    | Administrador y Operaciones Comerciales pueden cancelar.
    | La ruta también exige permiso:pedidos.cancelar.
    */

    public function cancelar(
        Request $request,
        Pedido $pedido
    ): RedirectResponse {
        abort_unless(
            in_array(
                $request->user()->role?->nombre,
                ['Administrador', 'Operaciones Comerciales'],
                true
            ),
            403
        );

        $data = $request->validate([
            'motivo' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
        ]);

        $this->actualizarEstado(
            $pedido,
            'cancelado',
            $request,
            $data['motivo']
        );

        return back()->with(
            'success',
            'El pedido fue cancelado; el registro se conserva.'
        );
    }

    public function imprimirDos(Request $request): View
    {
        $data = $request->validate([
            'pedido_1' => [
                'required',
                'integer',
                'exists:pedidos,id',
            ],
            'pedido_2' => [
                'required',
                'integer',
                'exists:pedidos,id',
                'different:pedido_1',
            ],
        ]);

        $pedidos = Pedido::with(['vendedor', 'detalles'])
            ->whereIn('id', [
                $data['pedido_1'],
                $data['pedido_2'],
            ])
            ->get()
            ->keyBy('id');

        return view('orders.print-two', [
            'pedidos' => collect([
                $pedidos[$data['pedido_1']],
                $pedidos[$data['pedido_2']],
            ]),
        ]);
    }

    private function actualizarEstado(
        Pedido $pedido,
        string $nuevo,
        Request $request,
        ?string $motivo
    ): void {
        app(TransicionesPedido::class)->registrar(
            $pedido, null, $nuevo, $request->user()->id, [], $motivo
        );
    }

    private function opcionesFormulario(): array
    {
        $clientes = Cliente::where('estado', true)
            ->orWhere('id', request()->route('pedido')?->cliente_id)
            ->orderBy('nombre')
            ->get();

        $presentaciones = PresentacionProducto::with([
            'producto',
            'variante',
        ])
            ->where('estado', true)
            ->whereHas(
                'producto',
                fn ($q) => $q->where('estado', true)
            )
            ->whereNotNull('precio_unitario')
            ->where(function ($q) {
                $q->where('venta_por_unidad', true)
                    ->orWhere(function ($empaque) {
                        $empaque->where('venta_por_empaque', true)
                            ->whereNotNull('unidades_por_empaque')
                            ->where('unidades_por_empaque', '>', 0);
                    });
            })
            ->where(function ($q) {
                $q->whereNull('variante_producto_id')
                    ->orWhereHas(
                        'variante',
                        fn ($q) => $q->where('estado', true)
                    );
            })
            ->orWhereIn('id', request()->route('pedido')?->detalles->pluck('presentacion_producto_id')->filter()->all() ?? [])
            ->orderBy('codigo_sku')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->codigo_sku,
                    'nombre' => $p->producto->nombre
                        .($p->variante
                            ? ' · '.$p->variante->valor
                            : '')
                        .' · '.$p->contenido.' '
                        .$p->unidad_medida,
                    'unidad' => (bool) $p->venta_por_unidad && $p->precio_unitario !== null,
                    'empaque' => (bool) $p->venta_por_empaque && $p->precio_empaque !== null && (int) $p->unidades_por_empaque > 0,
                    'precio_unidad' => $p->precio_unitario,
                    'precio_empaque' => $p->precio_empaque,
                    'unidades_por_empaque' => $p->unidades_por_empaque,
                    'tipo_empaque' => $p->tipo_empaque ?: 'Empaque',
                ];
            })
            ->values()
            ->all();

        $formatosSurtidos = request()->user()?->role?->tienePermiso('pedidos.surtidos.componer') ? app(GestionarSurtidos::class)->formatos(request()->route('pedido') instanceof Pedido ? request()->route('pedido')->detalles->pluck('formato_surtido_id')->filter()->all() : []) : [];

        return compact('clientes', 'presentaciones', 'formatosSurtidos');
    }
}
