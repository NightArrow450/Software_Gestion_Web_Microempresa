<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Services\CalculadoraPedido;
use App\Services\GestionarSurtidos;
use App\Services\RegistroAuditoria;
use App\Services\TransicionesPedido;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        return view(
            'orders.create',
            $this->opcionesFormulario()
        );
    }

    public function store(
        Request $request,
        CalculadoraPedido $calculadora
    ): RedirectResponse {
        $data = $this->validarPedido($request);

        $pedido = DB::transaction(function () use (
            $data,
            $request,
            $calculadora
        ) {
            $cliente = Cliente::whereKey($data['cliente_id'])
                ->where('estado', true)
                ->first();

            if (! $cliente) {
                throw ValidationException::withMessages([
                    'cliente_id' => 'Selecciona un cliente activo.',
                ]);
            }

            [$detalles, $total] = $this->prepararDetalles(
                $data['detalles'],
                $calculadora
            );

            $pedido = Pedido::create(array_merge(
                $this->cabecera($data, $cliente),
                [
                    'usuario_id' => $request->user()->id,
                    'estado' => 'registrado',
                    'total' => $total,
                ]
            ));

            $this->persistirDetalles($pedido, $detalles, $data['detalles']);

            $pedido->historialEstados()->create([
                'usuario_id' => $request->user()->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'registrado',
                'motivo' => 'Pedido registrado.',
            ]);

            return $pedido;
        });

        return redirect()
            ->route('pedidos.show', $pedido)
            ->with(
                'success',
                'Pedido N.º '.$pedido->numero.' registrado correctamente.'
            );
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

        return view('orders.show', compact('pedido'));
    }

    public function edit(Pedido $pedido): View
    {
        abort_unless(
            in_array(
                $pedido->estado,
                ['registrado', 'observado'],
                true
            ),
            403,
            'Solo pueden editarse pedidos registrados u observados.'
        );

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
        Pedido $pedido,
        CalculadoraPedido $calculadora
    ): RedirectResponse {
        $data = $this->validarPedido($request);

        DB::transaction(function () use (
            $pedido,
            $data,
            $calculadora
        ) {
            $pedido = Pedido::whereKey($pedido->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array(
                $pedido->estado,
                ['registrado', 'observado'],
                true
            )) {
                throw ValidationException::withMessages([
                    'pedido' => 'Ya no se puede editar un pedido procesado.',
                ]);
            }

            $cliente = Cliente::whereKey($data['cliente_id'])
                ->where('estado', true)
                ->first();

            if (! $cliente) {
                throw ValidationException::withMessages([
                    'cliente_id' => 'Selecciona un cliente activo.',
                ]);
            }

            [$detalles, $total] = $this->prepararDetalles(
                $data['detalles'],
                $calculadora,
                $pedido
            );

            $pedido->update(array_merge(
                $this->cabecera($data, $cliente),
                ['total' => $total]
            ));

            $this->persistirDetalles($pedido, $detalles, $data['detalles'], true);
        });

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

    private function validarPedido(Request $request): array
    {
        $hoy = CarbonImmutable::today('America/Lima');
        $datos = $request->validate([
            'cliente_id' => [
                'required',
                'integer',
                'exists:clientes,id',
            ],
            'fecha_pedido' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$hoy->subDays(30)->toDateString(),
                'before_or_equal:'.$hoy->toDateString(),
            ],
            'fecha_entrega' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:fecha_pedido',
            ],
            'condicion_pago' => [
                'required',
                Rule::in(['contado', 'credito']),
            ],
            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'detalles' => [
                'required',
                'array',
                'min:1',
                'max:18',
            ],
            'detalles.*.tipo_linea' => ['sometimes', 'in:normal,surtido'],
            'detalles.*.formato_surtido_id' => ['nullable', 'integer'],
            'detalles.*.id' => ['nullable', 'integer'],
            'detalles.*.paquetes_json' => ['nullable', 'string', 'max:3000000'],
            'detalles.*.presentacion_producto_id' => [
                'required_unless:detalles.*.tipo_linea,surtido',
                'nullable',
                'integer',
                'exists:presentaciones_producto,id',
            ],
            'detalles.*.modalidad' => [
                'required',
                Rule::in(['unidad', 'empaque']),
            ],
            'detalles.*.cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
            ],
            'detalles.*.unidades_por_empaque' => [
                'exclude_unless:detalles.*.modalidad,empaque',
                'nullable', 'integer', 'min:2', 'max:1000',
            ],
        ], [
            'detalles.*.unidades_por_empaque.integer' => 'Las unidades por empaque deben ser un número entero entre 2 y 1000.',
            'detalles.*.unidades_por_empaque.min' => 'El empaque debe contener entre 2 y 1000 unidades.',
            'detalles.*.unidades_por_empaque.max' => 'El empaque debe contener entre 2 y 1000 unidades.',
            'detalles.max' => 'La nota física admite hasta 18 líneas de productos por pedido.',
            'fecha_entrega.after_or_equal' => 'La fecha de entrega no puede ser anterior a la fecha del pedido.',
        ]);

        $datos['detalles'] = array_values($datos['detalles']);
        $limiteEntrega = CarbonImmutable::parse($datos['fecha_pedido'], 'America/Lima')->addDays(90);
        if (CarbonImmutable::parse($datos['fecha_entrega'], 'America/Lima')->greaterThan($limiteEntrega)) {
            throw ValidationException::withMessages([
                'fecha_entrega' => 'La fecha de entrega no puede superar los 90 días desde la fecha del pedido.',
            ]);
        }

        return $datos;
    }

    private function prepararDetalles(
        array $filas,
        CalculadoraPedido $calculadora,
        ?Pedido $pedido = null
    ): array {
        $presentaciones = PresentacionProducto::with([
            'producto',
            'variante',
        ])
            ->whereIn(
                'id',
                array_column($filas, 'presentacion_producto_id')
            )
            ->get()
            ->keyBy('id');

        $detalles = [];
        $combinaciones = [];
        $totalCentimos = 0;

        foreach ($filas as $i => $fila) {
            if (($fila['tipo_linea'] ?? 'normal') === 'surtido') {
                $actual = $pedido?->detalles->firstWhere('id', (int) ($fila['id'] ?? 0));
                $linea = app(GestionarSurtidos::class)->prepararLinea($fila, request()->user(), $actual);
                $clave = 'surtido|'.$linea['formato_surtido_id'].'|'.$linea['unidades_por_empaque'];
                if (isset($combinaciones[$clave])) {
                    throw ValidationException::withMessages(['detalles' => 'Agrupa los paquetes del mismo formato y tamaño en una línea, conservando sus composiciones individuales.']);
                }
                $combinaciones[$clave] = true;
                $detalles[] = $linea;
                $totalCentimos += (int) round((float) $linea['importe'] * 100);

                continue;
            }
            $presentacion = $presentaciones->get(
                (int) $fila['presentacion_producto_id']
            );

            if (
                ! $presentacion ||
                ! $presentacion->estado ||
                ! $presentacion->producto?->estado ||
                (
                    $presentacion->variante &&
                    ! $presentacion->variante->estado
                )
            ) {
                throw ValidationException::withMessages([
                    "detalles.{$i}.presentacion_producto_id" => 'Esta presentación está inactiva.',
                ]);
            }

            $empaque = $fila['modalidad'] === 'empaque';
            $tamanoEmpaque = $empaque ? ($fila['unidades_por_empaque'] ?? $presentacion->unidades_por_empaque) : null;

            if ($empaque && (filter_var($tamanoEmpaque, FILTER_VALIDATE_INT) === false || $tamanoEmpaque < 2 || $tamanoEmpaque > 1000)) {
                throw ValidationException::withMessages([
                    "detalles.{$i}.unidades_por_empaque" => 'El empaque debe contener entre 2 y 1000 unidades. Indica un tamaño válido.',
                ]);
            }

            $clave = $presentacion->id.'|'.$fila['modalidad'].'|'.($empaque ? (int) $tamanoEmpaque : '');
            if (isset($combinaciones[$clave])) {
                throw ValidationException::withMessages([
                    "detalles.{$i}.presentacion_producto_id" => 'Esta presentación, modalidad y tamaño de empaque ya están registrados en otra fila.',
                ]);
            }
            $combinaciones[$clave] = true;

            if (
                (
                    $empaque &&
                    (
                        ! $presentacion->venta_por_empaque
                    )
                ) ||
                (
                    ! $empaque &&
                    ! $presentacion->venta_por_unidad
                )
            ) {
                throw ValidationException::withMessages([
                    "detalles.{$i}.modalidad" => 'Forma de venta no habilitada.',
                ]);
            }

            $precio = $empaque
                ? $presentacion->precio_empaque
                : $presentacion->precio_unitario;

            if ($empaque) {
                if ($presentacion->precio_unitario === null) {
                    throw ValidationException::withMessages([
                        "detalles.{$i}.modalidad" => 'Registra el precio unitario de la presentación para calcular el precio del empaque.',
                    ]);
                }
                if ((int) $tamanoEmpaque !== (int) $presentacion->unidades_por_empaque) {
                    $precio = $calculadora->calcularImporte((int) $tamanoEmpaque, $presentacion->precio_unitario);
                }
            }

            if ($precio === null) {
                throw ValidationException::withMessages([
                    "detalles.{$i}.modalidad" => 'Debes registrar primero el precio en el catálogo.',
                ]);
            }

            $importe = $calculadora->calcularImporte(
                (int) $fila['cantidad'],
                (string) $precio
            );

            if ((float) $importe > 999999.99) {
                throw ValidationException::withMessages([
                    "detalles.{$i}.cantidad" => 'El importe de una línea no puede superar S/ 999 999,99.',
                ]);
            }

            $totalCentimos += (int) round(
                (float) $importe * 100
            );

            $descripcion = $presentacion->producto->nombre;

            if ($presentacion->variante) {
                $descripcion .= ' · '.$presentacion->variante->valor;
            }

            $descripcion .= ' · '
                .$presentacion->contenido.' '
                .$presentacion->unidad_medida;

            if ($empaque) {
                $descripcion .= ' · '
                    .($presentacion->tipo_empaque ?: 'Empaque')
                    .' de '.$tamanoEmpaque;
            }

            $detalles[] = [
                'presentacion_producto_id' => $presentacion->id,
                'descripcion_producto' => mb_substr(
                    $descripcion,
                    0,
                    255
                ),
                'codigo_sku' => $presentacion->codigo_sku,
                'modalidad' => $fila['modalidad'],
                'unidades_por_empaque' => $empaque ? (int) $tamanoEmpaque : null,
                'cantidad' => $fila['cantidad'],
                'precio_unitario' => $precio,
                'importe' => $importe,
            ];
        }

        return [
            $detalles,
            number_format($totalCentimos / 100, 2, '.', ''),
        ];
    }

    private function cabecera(
        array $data,
        Cliente $cliente
    ): array {
        return [
            'cliente_id' => $cliente->id,
            'fecha_pedido' => $data['fecha_pedido'],
            'fecha_entrega' => $data['fecha_entrega'],
            'condicion_pago' => $data['condicion_pago'],
            'observaciones' => $data['observaciones'] ?? null,
            'cliente_nombre' => $cliente->nombre,
            'cliente_documento' => $cliente->documento,
            'cliente_telefono' => $cliente->telefono,
            'cliente_mercado' => $cliente->mercado,
            'cliente_puesto_referencia' => $cliente->puesto_referencia,
            'cliente_distrito' => $cliente->distrito,
            'cliente_direccion' => $cliente->direccion,
        ];
    }

    private function persistirDetalles(Pedido $pedido, array $detalles, array $filas, bool $editar = false): void
    {
        $anteriores = $editar ? $pedido->detalles->keyBy('id') : collect();
        $haySurtidos = $anteriores->contains(fn ($d): bool => $d->tipo_linea === 'surtido') || collect($detalles)->contains(fn (array $d): bool => ($d['tipo_linea'] ?? 'normal') === 'surtido');
        if (! $haySurtidos) {
            if ($editar) {
                $pedido->detalles()->delete();
            }
            $pedido->detalles()->createMany($detalles);

            return;
        }
        $servicio = app(GestionarSurtidos::class);
        $servicio->autorizar(request()->user());
        $vistos = [];
        foreach ($detalles as $i => $datos) {
            $id = (int) ($filas[$i]['id'] ?? 0);
            $actual = $id > 0 ? $anteriores->get($id) : null;
            if ($id > 0 && (! $actual || isset($vistos[$id]))) {
                throw ValidationException::withMessages(['detalles' => 'Una línea está repetida o no pertenece a este pedido.']);
            }
            $mismoTipo = $actual && ($actual->tipo_linea ?? 'normal') === ($datos['tipo_linea'] ?? 'normal') && $actual->formato_surtido_id === ($datos['formato_surtido_id'] ?? null);
            if ($mismoTipo) {
                $actual->update($datos);
                $detalle = $actual;
                $vistos[$id] = true;
            } else {
                $detalle = $pedido->detalles()->create($datos);
            }
            if ($detalle->tipo_linea === 'surtido') {
                $servicio->guardar($detalle, $servicio->leerPaquetes($filas[$i]['paquetes_json'] ?? null), request()->user(), $editar ? 'Composición actualizada en la edición comercial del pedido.' : 'Composición seleccionada durante el registro del pedido.');
            }
        }
        foreach ($anteriores as $id => $anterior) {
            if (! isset($vistos[$id])) {
                if ($anterior->tipo_linea === 'surtido') {
                    $anterior->update(['retirado_en' => now()]);
                    DB::table('pedido_paquetes_surtidos')->where('pedido_detalle_id', $id)->update(['activo' => false]);
                    RegistroAuditoria::registrar('linea_surtida_retirada', 'pedido_detalle', $id, ['pedido_id' => $pedido->id, 'motivo' => 'Retirada durante edición comercial; se conserva el historial.']);
                } else {
                    $anterior->delete();
                }
            }
        }
    }

    private function opcionesFormulario(): array
    {
        $clientes = Cliente::where('estado', true)
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
