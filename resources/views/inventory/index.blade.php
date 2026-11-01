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
        <p>Disponibilidad operativa de productos terminados registrados. No representa líquido en bidones ni reservas para clientes.</p></div>
</div>
<section class="filters">
    <form action="{{ route('inventario.index') }}" method="GET">
        @if($insumos)<input type="hidden" name="historico_materiales" value="1">@endif
        <div class="filters-grid">
            <div class="form-group">
                <label class="form-label" for="buscar">{{ $insumos ? 'Código, producto o material histórico' : 'Código o producto terminado' }}</label>
                <input class="form-control" name="buscar" id="buscar" value="{{ $buscar }}" placeholder="Buscar producto, SKU, galón, botella...">
            </div>
            <div class="form-group">
                <label class="form-label" for="pedido_id">Consultar pedido pendiente de despacho</label>
                <select name="pedido_id" id="pedido_id" class="form-control">
                    <option value="">Sin pedido</option>
                    @if($pedidoConsulta && !$pedidosDisponibles->contains('id', $pedidoConsulta->id))<option value="{{ $pedidoConsulta->id }}" selected>N.º {{ $pedidoConsulta->numero }} — {{ $pedidoConsulta->cliente_nombre }}</option>@endif
                    @foreach($pedidosDisponibles as $pedido)
                        <option value="{{ $pedido->id }}" @selected((int)request('pedido_id') === $pedido->id)>
                            N.º {{ $pedido->numero }} — {{ $pedido->cliente_nombre }} ({{ $pedido->estado }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        {{ $pedidosDisponibles->links() }}
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
    <p class="s3-muted">La consulta es informativa: no reserva ni descuenta stock. Se descuentan los despachos ya registrados de la demanda vigente. Las referencias históricas por empaque conservan su tamaño original.</p>
    <div class="table-container"><table class="app-table">
        <thead><tr><th>SKU</th><th>Producto</th><th>Pendiente total</th><th>En espera</th><th>Pendiente activo</th><th>Stock terminado registrado</th><th>Faltante activo / total</th><th>Resultado</th></tr></thead>
        <tbody>@forelse($revision as $fila)
            <tr><td>{{ $fila['sku'] }}</td><td>{{ $fila['nombre'] }}</td>
                <td>{{ $fila['necesario'] ?? 'No verificable' }}</td><td>{{ $fila['en_espera'] }}</td><td>{{ $fila['activo'] ?? 'No verificable' }}</td><td>{{ $fila['disponible'] }}</td><td>{{ $fila['faltante_activo'] ?? 'No verificable' }} / {{ $fila['faltante_total'] ?? 'No verificable' }}</td>
                <td><span class="s3-status {{ $fila['alcanza'] ? 's3-ok' : 's3-danger' }}">{{ $fila['alcanza'] ? 'Suficiente' : ($fila['error'] ?: ($fila['en_espera'] > 0 ? 'Hay unidades en espera; atención pendiente' : 'Insuficiente')) }}</span></td>
            </tr>
        @empty<tr><td colspan="8">Este pedido no tiene demanda pendiente.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@endif
<section class="table-card">
    <div class="table-header"><strong>Existencias por presentación</strong><span class="muted">{{ $presentaciones->total() }} presentación(es) activas</span></div>
    <div class="table-container"><table class="app-table">
        <thead><tr><th>SKU</th><th>Producto</th><th>Presentación</th><th>Unidades terminadas registradas</th></tr></thead>
        <tbody>@forelse($presentaciones as $p)
            @php $stock = (int) ($cantidades[$p->id] ?? 0); $factor=(int)$p->unidades_por_empaque; @endphp
            <tr><td><strong>{{ $p->codigo_sku }}</strong></td>
                <td>{{ $p->producto?->nombre }} {{ $p->variante ? '· '.$p->variante->valor : '' }}</td>
                <td>{{ $p->contenido }} {{ $p->unidad_medida }}</td>
                <td><strong>{{ $stock }}</strong></td>
            </tr>
        @empty<tr><td colspan="4">No hay presentaciones activas que coincidan.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="s3-pagination">{{ $presentaciones->links() }}</div>
</section>

<section class="form-section"><h2>Historial de materiales</h2><p>Los materiales no forman parte de la disponibilidad operativa de productos terminados. No se interpretan sus saldos como existencias físicas verificadas.</p><a class="btn btn-secondary" href="{{ route('inventario.index', array_merge(request()->except(['page', 'insumos_pagina']), ['historico_materiales' => $insumos ? 0 : 1])) }}">{{ $insumos ? 'Ocultar saldos históricos de materiales' : 'Consultar saldos históricos de materiales' }}</a></section>
@if($insumos)
<section class="table-card s3-panel">
    @unless(config('envasado.control_materiales_exacto'))<p>Los saldos de insumos son registros históricos y pueden no representar las existencias físicas. No se actualizan automáticamente al envasar ni generan alertas.</p>@endunless
    <div class="table-header">
        <strong>Existencias físicas no verificadas · historial de materiales</strong>
        <div class="s3-actions" style="margin-top:0">
            <span class="muted">{{ $insumos->total() }} insumo(s)</span>
            @if(auth()->user()->role?->tienePermiso('inventario.ajustar'))
                <a class="btn btn-primary" href="{{ route('inventario.insumos.create') }}">+ Registrar insumo</a>
            @endif
        </div>
    </div>
    <div class="table-container"><table class="app-table">
        <thead><tr><th>Código</th><th>Material / Insumo</th><th>Categoría</th><th>Saldo histórico registrado</th><th>Unidad</th></tr></thead>
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

@endif
@endsection
