@extends('layouts.app')
@section('title', 'Disponibilidad de inventario')
@section('page-title', 'Disponibilidad de inventario')
@section('breadcrumb', 'Inicio / Inventario / Disponibilidad')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/sprint3.css') }}">
@endpush
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">Inventario · HU-04</div><h1>Consultar disponibilidad</h1>
        <p>Existencias de productos terminados y de insumos (envases, tapas, etiquetas y materias primas).</p></div>
</div>
<section class="filters">
    <form action="{{ route('inventario.index') }}" method="GET">
        <div class="filters-grid">
            <div class="form-group">
                <label class="form-label" for="buscar">Código, producto o insumo</label>
                <input class="form-control" name="buscar" id="buscar" value="{{ $buscar }}" placeholder="Buscar producto, SKU, galón, botella...">
            </div>
            <div class="form-group">
                <label class="form-label" for="pedido_id">Verificar pedido no cancelado</label>
                <select name="pedido_id" id="pedido_id" class="form-control">
                    <option value="">Sin pedido</option>
                    @foreach($pedidosDisponibles as $pedido)
                        <option value="{{ $pedido->id }}" @selected((int)request('pedido_id') === $pedido->id)>
                            N.º {{ $pedido->numero }} — {{ $pedido->cliente_nombre }} ({{ $pedido->estado }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="s3-actions">
            <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">search</span> Consultar</button>
            <a class="btn btn-secondary" href="{{ route('inventario.index') }}">Limpiar</a>
        </div>
    </form>
</section>
@if($pedidoConsulta)
<section class="form-section s3-panel">
    <h2 class="form-section-title">Disponibilidad del pedido N.º {{ $pedidoConsulta->numero }}</h2>
    <p class="s3-status {{ $puedeAtender ? 's3-ok' : 's3-danger' }}">
        {{ $puedeAtender ? 'Disponible para atender con el stock actual' : 'Stock insuficiente o datos incompletos' }}
    </p>
    <p class="s3-muted">La consulta es informativa: no reserva ni descuenta stock. Las cantidades de cajas/paquetes se convierten a unidades.</p>
    <div class="table-container"><table class="app-table">
        <thead><tr><th>SKU</th><th>Producto</th><th>Requerido (unidades)</th><th>Disponible</th><th>Resultado</th></tr></thead>
        <tbody>@forelse($revision as $fila)
            <tr><td>{{ $fila['sku'] }}</td><td>{{ $fila['nombre'] }}</td>
                <td>{{ $fila['necesario'] }}</td><td>{{ $fila['disponible'] }}</td>
                <td><span class="s3-status {{ $fila['alcanza'] ? 's3-ok' : 's3-danger' }}">{{ $fila['alcanza'] ? 'Suficiente' : ($fila['error'] ?: 'Insuficiente') }}</span></td>
            </tr>
        @empty<tr><td colspan="5">Este pedido no tiene detalle.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@endif
<section class="table-card">
    <div class="table-header"><strong>Existencias por presentación</strong><span class="muted">{{ $presentaciones->total() }} presentación(es) activas</span></div>
    <div class="table-container"><table class="app-table">
        <thead><tr><th>SKU</th><th>Producto</th><th>Presentación</th><th>Stock (unidades)</th><th>Cajas completas</th></tr></thead>
        <tbody>@forelse($presentaciones as $p)
            @php $stock = (int) ($cantidades[$p->id] ?? 0); $factor=(int)$p->unidades_por_empaque; @endphp
            <tr><td><strong>{{ $p->codigo_sku }}</strong></td>
                <td>{{ $p->producto?->nombre }} {{ $p->variante ? '· '.$p->variante->valor : '' }}</td>
                <td>{{ $p->contenido }} {{ $p->unidad_medida }}</td>
                <td><strong>{{ $stock }}</strong></td>
                <td>{{ $p->venta_por_empaque && $factor > 0 ? intdiv($stock, $factor) : '—' }}</td>
            </tr>
        @empty<tr><td colspan="5">No hay presentaciones activas que coincidan.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="s3-pagination">{{ $presentaciones->links() }}</div>
</section>

<section class="table-card s3-panel">
    @unless(config('envasado.control_materiales_exacto'))<p>Los saldos de insumos son registros históricos y pueden no representar las existencias físicas. No se actualizan automáticamente al envasar ni generan alertas.</p>@endunless
    <div class="table-header">
        <strong>Insumos y materiales</strong>
        <div class="s3-actions" style="margin-top:0">
            <span class="muted">{{ $insumos->total() }} insumo(s)</span>
            @if(auth()->user()->role?->tienePermiso('inventario.ajustar'))
                <a class="btn btn-primary" href="{{ route('inventario.insumos.create') }}">+ Registrar insumo</a>
            @endif
        </div>
    </div>
    <div class="table-container"><table class="app-table">
        <thead><tr><th>Código</th><th>Material / Insumo</th><th>Categoría</th><th>{{ config('envasado.control_materiales_exacto') ? 'Cantidad actual' : 'Saldo registrado sin verificación física' }}</th><th>Unidad</th></tr></thead>
        <tbody>
        @forelse($insumos as $insumo)
            <tr><td><strong>{{ $insumo->codigo }}</strong></td><td>{{ $insumo->nombre }}</td>
                <td>{{ $insumo->categoria }}</td>
                <td><strong>{{ number_format((float) $insumo->cantidad_actual, 3, '.', '') }}</strong></td>
                <td>{{ $insumo->unidad_medida }}</td></tr>
        @empty
            <tr><td colspan="5">No hay insumos registrados. Usa «Registrar insumo» para dar de alta botellas, galones, etiquetas o químicos.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="s3-pagination">{{ $insumos->links() }}</div>
</section>

@endsection
