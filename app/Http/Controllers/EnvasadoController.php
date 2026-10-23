<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEnvasado;
use App\Models\Envasado;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Services\RegistrarEnvasado;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnvasadoController extends Controller
{
    public function index(Request $request): View
    {
        $datos = $request->validate(['pedido_id' => ['nullable', 'integer'], 'desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde']]);
        $registros = Envasado::with(['usuario', 'pedido'])->when($datos['pedido_id'] ?? null, fn ($q, $id) => $q->where('pedido_id', $id))->when($datos['desde'] ?? null, fn ($q, $fecha) => $q->where('created_at', '>=', $fecha.' 00:00:00'))->when($datos['hasta'] ?? null, fn ($q, $fecha) => $q->where('created_at', '<', CarbonImmutable::parse($fecha, 'America/Lima')->addDay()->startOfDay()))->orderByDesc('id')->paginate(15)->withQueryString();

        return view('envasado.index', compact('registros'));
    }

    public function create(): View
    {
        $presentaciones = PresentacionProducto::with('producto')->where('estado', true)->whereHas('producto', fn ($q) => $q->where('estado', true))->orderBy('codigo_sku')->get();
        $configuraciones = ConfiguracionEnvasado::where('estado', true)->orderBy('nombre')->get();
        $pedidos = Pedido::whereIn('estado', ['confirmado', 'en_preparacion'])->orderByDesc('id')->get();

        return view('envasado.create', compact('presentaciones', 'configuraciones', 'pedidos'));
    }

    public function previsualizar(Request $request, RegistrarEnvasado $servicio): View
    {
        $plan = $servicio->previsualizar($request->all());

        return view('envasado.confirmar', compact('plan'));
    }

    public function store(Request $request, RegistrarEnvasado $servicio): RedirectResponse
    {
        $envasado = $servicio->ejecutar($request->all(), $request->user());

        return redirect()->route('envasado.show', $envasado)->with('success', 'Envasado registrado: materiales descontados y producto terminado ingresado. No registres otra entrada por esta producción.');
    }

    public function show(Envasado $envasado): View
    {
        $envasado->load(['usuario', 'pedido', 'consumos', 'correcciones.materiales', 'correcciones.usuario', 'reportes.usuario']);
        $stock = Inventario::where('presentacion_producto_id', $envasado->presentacion_producto_id)->value('cantidad_actual') ?? 0;
        $solicitadas = $envasado->pedido ? $envasado->pedido->detalles()->with('presentacion')->where('presentacion_producto_id', $envasado->presentacion_producto_id)->get()->sum(fn ($d) => $d->cantidad * ($d->modalidad === 'empaque' ? $d->unidadesPorEmpaqueEfectivas($d->presentacion) : 1)) : null;

        return view('envasado.show',compact('envasado','stock','solicitadas'));
    }
}
