<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEnvasado;
use App\Models\Envasado;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\PresentacionProducto;
use App\Services\LecturaPedido;
use App\Services\RegistrarEnvasado;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EnvasadoController extends Controller
{
    public function index(Request $request): View
    {
        $datos = $request->validate(['pedido_id' => ['nullable', 'integer'], 'desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('desde') ? 'after_or_equal:desde' : null])]);
        $registros = Envasado::with(['usuario', 'pedido'])->when($datos['pedido_id'] ?? null, fn ($q, $id) => $q->where('pedido_id', $id))->when($datos['desde'] ?? null, fn ($q, $fecha) => $q->where('created_at', '>=', $fecha.' 00:00:00'))->when($datos['hasta'] ?? null, fn ($q, $fecha) => $q->where('created_at', '<', CarbonImmutable::parse($fecha, 'America/Lima')->addDay()->startOfDay()))->orderByDesc('id')->paginate(15)->withQueryString();

        return view('envasado.index', compact('registros'));
    }

    public function create(): View
    {
        $presentaciones = PresentacionProducto::with(['producto', 'variante'])->where('estado', true)
            ->whereHas('producto', fn (Builder $consulta): Builder => $consulta->where('estado', true))
            ->where(fn (Builder $consulta): Builder => $consulta->whereNull('variante_producto_id')
                ->orWhereHas('variante', fn (Builder $variante): Builder => $variante->where('estado', true)))
            ->orderBy('codigo_sku')->get();
        $configuraciones = config('envasado.control_materiales_exacto') ? ConfiguracionEnvasado::with('presentacion')->where('estado', true)
            ->whereIn('presentacion_producto_id', $presentaciones->modelKeys())->orderBy('nombre')->get() : collect();
        $pedidos = Pedido::whereIn('estado', ['confirmado', 'en_preparacion', 'despacho_parcial'])->orderByDesc('id')->get();

        return view('envasado.create', compact('presentaciones', 'configuraciones', 'pedidos'));
    }

    public function previsualizar(Request $request, RegistrarEnvasado $servicio): View
    {
        $filtrosLista = $this->filtrosLista($request);
        $plan = $servicio->previsualizar($request->all());

        if ($filtrosLista !== null) {
            return view('envasado.partials.confirmacion', ['plan' => $plan, 'integrado' => true, 'filtrosLista' => $filtrosLista]);
        }

        return view('envasado.confirmar', compact('plan'));
    }

    public function store(Request $request, RegistrarEnvasado $servicio): RedirectResponse
    {
        $filtrosLista = $this->filtrosLista($request);
        $envasado = $servicio->ejecutar($request->all(), $request->user());

        if ($filtrosLista !== null) {
            if (! empty($filtrosLista['volver_pedido_id'])) {
                return redirect()->route('cumplimiento.show', $filtrosLista['volver_pedido_id'])->with('success', 'Producción física registrada una sola vez. El stock ya incluye estas unidades.');
            }

            return redirect()->route('cumplimiento.lista-preparacion', $filtrosLista)->with('success', 'Unidades terminadas registradas y stock actualizado; no registres otra entrada por esta producción.');
        }

        return redirect()->route('envasado.show', $envasado)->with('success', 'Envasado registrado: producto terminado ingresado. No registres otra entrada por esta producción.');
    }

    /** @return array<string, string|null>|null */
    private function filtrosLista(Request $request): ?array
    {
        $request->validate(['desde_lista' => ['sometimes', 'boolean']]);
        if (! $request->boolean('desde_lista')) {
            return null;
        }
        abort_unless($request->user()->loadMissing('role.permisos')->role?->permisos->contains('nombre', 'pedidos.ver'), 403);

        $filtros = $request->validate([
            'volver_pedido_id' => ['nullable', 'integer', 'exists:pedidos,id'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('fecha_desde') ? ['after_or_equal:fecha_desde'] : [])],
            'produccion_desde' => ['required', 'date_format:Y-m-d'],
            'produccion_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:produccion_desde'],
        ]);

        return array_map(fn ($valor): string => (string) ($valor ?? ''), $filtros);
    }

    public function show(Envasado $envasado): View
    {
        $envasado->load(['usuario', 'pedido', 'consumos', 'correcciones.materiales', 'correcciones.usuario', 'reportes.usuario']);
        $stock = Inventario::where('presentacion_producto_id', $envasado->presentacion_producto_id)->value('cantidad_actual') ?? 0;
        $solicitadas = $envasado->pedido ? app(LecturaPedido::class)->lineasFisicas($envasado->pedido)->where('presentacion_producto_id', $envasado->presentacion_producto_id)->sum(fn ($d) => $d->cantidad * ($d->modalidad === 'empaque' ? $d->unidadesPorEmpaqueEfectivas($d->presentacion) : 1)) : null;

        $netoAcumulado = $envasado->pedido_id === null ? null : (int) Envasado::query()
            ->where('pedido_id', $envasado->pedido_id)
            ->where('presentacion_producto_id', $envasado->presentacion_producto_id)
            ->sum(DB::raw('cantidad - unidades_revertidas'));

        return view('envasado.show', compact('envasado', 'stock', 'solicitadas', 'netoAcumulado'));
    }
}
