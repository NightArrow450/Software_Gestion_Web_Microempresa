@extends('layouts.app')
@section('title', 'Gestión de Pedidos')
@section('page-title', 'Gestión de Pedidos')
@section('breadcrumb', 'Inicio / Pedidos')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint2.css') }}">@endpush
@section('content')
@php
    $permisos = auth()->user()->role?->permisos?->pluck('nombre')->all() ?? [];
    $puedeCrear = in_array('pedidos.crear', $permisos);
@endphp
<div class="page-heading"><div><div class="eyebrow">Operaciones comerciales</div><h1>Gestión de Pedidos</h1>
    <p>Consulta, procesa e imprime las notas de pedido de DHACER ECO LIMP.</p></div>
    @if($puedeCrear)<a class="btn btn-primary" href="{{ route('pedidos.create') }}"><span class="material-symbols-outlined">add_shopping_cart</span> Nuevo pedido</a>@endif
</div>
<section class="filters"><form method="GET" action="{{ route('pedidos.index') }}">
    <div class="filters-grid">
        <div class="form-group"><label class="form-label" for="search">Cliente o número de pedido</label><input class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Nombre, teléfono, 000001..."></div>
        <div class="form-group"><label class="form-label" for="estado">Estado</label><select class="form-control" id="estado" name="estado">
            <option value="">Todos</option>@foreach(\App\EstadoPedido::etiquetas() as $clave=>$etiqueta)
                <option value="{{ $clave }}" @selected(request('estado') === $clave)>{{ $etiqueta }}</option>@endforeach</select></div>
        <div class="form-group"><label class="form-label" for="fecha_entrega">Fecha de entrega</label><input class="form-control" type="date" id="fecha_entrega" name="fecha_entrega" value="{{ request('fecha_entrega') }}"></div>
    </div><div class="s2-actions"><button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">filter_alt</span> Aplicar filtros</button><a class="btn btn-secondary" href="{{ route('pedidos.index') }}">Limpiar</a></div>
</form></section>
<section class="table-card"><div class="table-header"><strong>Pedidos registrados</strong><div class="muted">{{ $pedidos->total() }} resultado(s)</div></div>
<div class="table-container"><table class="app-table"><thead><tr><th>Número</th><th>Cliente</th><th>Vendedor</th><th>Entrega</th><th>Total</th><th>Estado</th><th style="text-align:right">Acciones</th></tr></thead>
<tbody>@forelse($pedidos as $pedido)
<tr><td><strong>N.º {{ $pedido->numero }}</strong></td><td>{{ $pedido->cliente_nombre }}</td><td>{{ $pedido->vendedor?->first_name }} {{ $pedido->vendedor?->last_name }}</td>
<td>{{ $pedido->fecha_entrega->format('d/m/Y') }}</td><td><strong>S/ {{ number_format((float)$pedido->total,2) }}</strong></td>
<td><span class="s2-state">{{ ucfirst(str_replace('_',' ',$pedido->estado)) }}</span></td>
<td style="text-align:right"><a class="btn btn-secondary" href="{{ route('pedidos.show',$pedido) }}">Ver detalle</a></td></tr>
@empty<tr><td colspan="7"><div class="empty-inline">Todavía no hay pedidos registrados.</div></td></tr>@endforelse</tbody>
</table></div><div class="s2-pagination">{{ $pedidos->links() }}</div></section>
<section class="form-section" style="margin-top:24px">
    <h2 class="form-section-title">Imprimir notas de pedido por fecha</h2>
    <p class="s2-note">
        Selecciona el día de los pedidos que deseas imprimir: hoy, el día 18
        u otra fecha. Se incluirán <strong>todos los pedidos no cancelados</strong>
        registrados ese día, ordenados por número, colocando
        <strong>dos notas distintas por cada hoja A4 horizontal</strong>.
        Si queda una nota impar, ocupará la mitad izquierda de la última hoja.
    </p>

    @if($errors->has('fecha_pedido'))
        <p class="s2-error" role="alert">{{ $errors->first('fecha_pedido') }}</p>
    @endif
    @if($errors->has('pedidos'))
        <p class="s2-error" role="alert">{{ $errors->first('pedidos') }}</p>
    @endif

    <form action="{{ route('pedidos.imprimir-por-fecha') }}"
          method="GET"
          target="_blank"
          class="s2-panel-actions"
          style="margin-top:15px">
        <div class="form-group">
            <label class="form-label" for="fecha_pedido_imprimir">Fecha del pedido</label>
            <input class="form-control"
                   type="date"
                   id="fecha_pedido_imprimir"
                   name="fecha_pedido"
                   value="{{ old('fecha_pedido', now('America/Lima')->toDateString()) }}"
                   required>
        </div>
        <button class="btn btn-primary" type="submit">
            <span class="material-symbols-outlined">print</span>
            Ver notas de esa fecha
        </button>
    </form>
    <p class="s2-note">No se imprimen pedidos con estado <strong>Cancelado</strong>, incluso si se intenta acceder mediante la URL antigua.</p>
</section>
@endsection
