<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PedidoController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('search', ''));

        $pedidos = Pedido::with('vendedor')
            ->when($busqueda !== '', function ($consulta) use ($busqueda) {
                $consulta->where(function ($filtro) use ($busqueda) {
                    $filtro->where(
                        'cliente_nombre',
                        'like',
                        "%{$busqueda}%"
                    );

                    if (ctype_digit($busqueda)) {
                        $filtro->orWhere('id', (int) $busqueda);
                    }
                });
            })
            ->when(
                $request->filled('fecha_entrega'),
                function ($consulta) use ($request) {
                    $consulta->whereDate(
                        'fecha_entrega',
                        $request->query('fecha_entrega')
                    );
                }
            )
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $opcionesImpresion = Pedido::latest('id')
            ->limit(200)
            ->get(['id', 'cliente_nombre']);

        return view(
            'orders.index',
            compact('pedidos', 'opcionesImpresion')
        );
    }

    public function create(): View
    {
        $clientes = Cliente::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $presentaciones = PresentacionProducto::with([
                'producto',
                'variante'
            ])
            ->where('estado', true)
            ->whereNotNull('precio_unitario')
            ->whereHas(
                'producto',
                fn ($consulta) => $consulta->where('estado', true)
            )
            ->where(function ($consulta) {
                $consulta
                    ->whereNull('variante_producto_id')
                    ->orWhereHas(
                        'variante',
                        fn ($variante) => $variante->where('estado', true)
                    );
            })
            ->orderBy('codigo_sku')
            ->get();

        return view(
            'orders.create',
            compact('clientes', 'presentaciones')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clientes', 'id')
                    ->where('estado', true),
            ],

            'fecha_pedido' => [
                'required',
                'date',
            ],

            'fecha_entrega' => [
                'required',
                'date',
                'after_or_equal:fecha_pedido',
            ],

            'condicion_pago' => [
                'required',
                Rule::in(['contado', 'credito']),
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'detalles' => [
                'required',
                'array',
                'min:1',
                'max:18',
            ],

            'detalles.*.presentacion_producto_id' => [
                'required',
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
        ], [
            'cliente_id.exists' =>
                'Selecciona un cliente activo.',

            'fecha_entrega.after_or_equal' =>
                'La entrega no puede ser anterior al pedido.',

            'detalles.min' =>
                'Agrega al menos un producto.',

            'detalles.max' =>
                'La nota admite un máximo de 18 renglones.',

            'detalles.*.cantidad.min' =>
                'La cantidad debe ser mayor que cero.',
        ]);

        $pedido = DB::transaction(function () use ($datos, $request) {

            $cliente = Cliente::whereKey($datos['cliente_id'])
                ->where('estado', true)
                ->first();

            if (!$cliente) {
                throw ValidationException::withMessages([
                    'cliente_id' => 'Selecciona un cliente activo.',
                ]);
            }

            $presentaciones = PresentacionProducto::with([
                    'producto',
                    'variante'
                ])
                ->whereIn(
                    'id',
                    array_column(
                        $datos['detalles'],
                        'presentacion_producto_id'
                    )
                )
                ->get()
                ->keyBy('id');

            $filas = [];
            $combinaciones = [];
            $totalCentimos = 0;

            foreach ($datos['detalles'] as $indice => $detalle) {

                $presentacion = $presentaciones->get(
                    (int) $detalle['presentacion_producto_id']
                );

                $modalidad = $detalle['modalidad'];
                $cantidad = (int) $detalle['cantidad'];

                $clave = $detalle['presentacion_producto_id']
                    .':'.$modalidad;

                if (isset($combinaciones[$clave])) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.presentacion_producto_id" =>
                            'No repitas la misma presentación y modalidad.',
                    ]);
                }

                $combinaciones[$clave] = true;

                // Verificar producto, variante y presentación activa.
                if (
                    !$presentacion ||
                    !$presentacion->estado ||
                    !$presentacion->producto?->estado ||
                    (
                        $presentacion->variante &&
                        !$presentacion->variante->estado
                    )
                ) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.presentacion_producto_id" =>
                            'Selecciona una presentación activa.',
                    ]);
                }

                if ($presentacion->precio_unitario === null) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.presentacion_producto_id" =>
                            'Esta presentación no tiene precio registrado.',
                    ]);
                }

                // Trabajamos en centavos para evitar errores
                // de precisión en los importes.
                $precioBaseCentimos = (int) round(
                    (float) $presentacion->precio_unitario * 100
                );

                if ($modalidad === 'unidad') {

                    if (!$presentacion->venta_por_unidad) {
                        throw ValidationException::withMessages([
                            "detalles.{$indice}.modalidad" =>
                                'No está habilitada la venta por unidad.',
                        ]);
                    }

                    $precioCentimos = $precioBaseCentimos;

                } else {

                    if (
                        !$presentacion->venta_por_empaque ||
                        (int) $presentacion->unidades_por_empaque < 1
                    ) {
                        throw ValidationException::withMessages([
                            "detalles.{$indice}.modalidad" =>
                                'No está habilitada la venta por caja o empaque.',
                        ]);
                    }

                    // Precio de caja = precio base x unidades por caja.
                    $precioCentimos = $precioBaseCentimos
                        * (int) $presentacion->unidades_por_empaque;
                }

                $importeCentimos = $precioCentimos * $cantidad;
                $totalCentimos += $importeCentimos;

                if ($totalCentimos > 999999999999) {
                    throw ValidationException::withMessages([
                        'detalles' =>
                            'El importe total supera el máximo permitido.',
                    ]);
                }

                // Guardamos una descripción histórica de la venta.
                $descripcion = $presentacion->producto->nombre;

                if ($presentacion->variante) {
                    $descripcion .= ' - '
                        .$presentacion->variante->valor;
                }

                $descripcion .= ' - '
                    .$presentacion->contenido.' '
                    .$presentacion->unidad_medida;

                if ($modalidad === 'empaque') {
                    $descripcion .= ' - '
                        .($presentacion->tipo_empaque ?: 'Caja')
                        .' x'
                        .$presentacion->unidades_por_empaque;
                }

                $filas[] = [
                    'presentacion_producto_id' => $presentacion->id,
                    'codigo_sku' => $presentacion->codigo_sku,
                    'descripcion_producto' =>
                        Str::limit($descripcion, 250, ''),
                    'modalidad' => $modalidad,
                    'cantidad' => $cantidad,

                    'precio_unitario' => number_format(
                        $precioCentimos / 100,
                        2,
                        '.',
                        ''
                    ),

                    'importe' => number_format(
                        $importeCentimos / 100,
                        2,
                        '.',
                        ''
                    ),
                ];
            }

            // Crear pedido con la información actual del cliente.
            $pedido = Pedido::create([
                'cliente_id' => $cliente->id,
                'usuario_id' => $request->user()->id,
                'fecha_pedido' => $datos['fecha_pedido'],
                'fecha_entrega' => $datos['fecha_entrega'],
                'condicion_pago' => $datos['condicion_pago'],
                'observaciones' => $datos['observaciones'] ?? null,
                'estado' => 'registrado',

                'total' => number_format(
                    $totalCentimos / 100,
                    2,
                    '.',
                    ''
                ),

                'cliente_nombre' => $cliente->nombre,
                'cliente_documento' => $cliente->documento,
                'cliente_telefono' => $cliente->telefono,
                'cliente_mercado' => $cliente->mercado,
                'cliente_puesto_referencia' =>
                    $cliente->puesto_referencia,
                'cliente_distrito' => $cliente->distrito,
                'cliente_direccion' => $cliente->direccion,
            ]);

            // Guardar todos los productos.
            $pedido->detalles()->createMany($filas);

            // Registrar el estado inicial.
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
                'Pedido N.º '.$pedido->numero
                .' registrado correctamente.'
            );
    }

    public function show(Pedido $pedido): View
    {
        $pedido->load([
            'vendedor',
            'detalles',
        ]);

        return view('orders.show', compact('pedido'));
    }

    public function imprimirDos(Request $request): View
    {
        $datos = $request->validate([
            'pedido_1' => [
                'required',
                'integer',
                'exists:pedidos,id',
            ],

            'pedido_2' => [
                'required',
                'integer',
                'different:pedido_1',
                'exists:pedidos,id',
            ],
        ]);

        // Dos pedidos diferentes en el orden elegido.
        // Imprimir no genera números nuevos.
        $pedidos = collect([
            Pedido::with(['vendedor', 'detalles'])
                ->findOrFail($datos['pedido_1']),

            Pedido::with(['vendedor', 'detalles'])
                ->findOrFail($datos['pedido_2']),
        ]);

        return view(
            'orders.print-two',
            compact('pedidos')
        );
    }
}

