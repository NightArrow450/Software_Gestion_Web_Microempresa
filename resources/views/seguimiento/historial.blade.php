@extends('layouts.app')
@section('title', 'Historial de pedidos')
@section('page-title', 'Historial de pedidos')
@section('breadcrumb', 'Inicio / Seguimiento / Historial')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint5.css') }}">@endpush
@section('content')
<div class="seg-top">
    <div>
        <div class="eyebrow">Sprint 5 · HU-12</div>
        <h1>Historial de pedidos</h1>
        <p class="seg-ayuda">Consulta pedidos anteriores; incluye pedidos cancelados para conservar los registros históricos.</p>
    </div>
    <a href="{{ route('seguimiento.index') }}" class="btn btn-secondary">Consultar estado actual</a>
</div>
<section class="seg-panel">
    <form method="GET" action="{{ route('seguimiento.historial') }}" class="seg-filtros">
        <div><label for="fecha_desde">Desde (fecha del pedido)</label><input id="fecha_desde" class="form-control" type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] }}"></div>
        <div><label for="fecha_hasta">Hasta</label><input id="fecha_hasta" class="form-control" type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] }}"></div>
        <div><label for="cliente">Nombre del cliente</label><input id="cliente" class="form-control" type="search" maxlength="180" name="cliente" value="{{ $filtros['cliente'] }}" placeholder="Buscar cliente"></div>
        <div><label for="estado">Estado</label><select id="estado" class="form-control" name="estado">
            <option value="">Todos los estados</option>
            @foreach($estados as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected($filtros['estado'] === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select></div>
        <div class="seg-botones"><button type="submit" class="btn btn-primary">Aplicar filtros</button>
            <a href="{{ route('seguimiento.historial') }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </form>
    @if($errors->any())<div style="margin-top:12px;color:#b42318">{{ $errors->first() }}</div>@endif
</section>
<section class="seg-panel">
    <p class="seg-ayuda">Resultados: {{ $pedidos->total() }} pedido(s). Se muestran 15 por página.</p>
    <div class="table-container"><table class="seg-tabla">
        <thead><tr><th>N.º</th><th>Fecha pedido</th><th>Cliente</th><th>Estado</th><th>Total</th><th>Consulta</th></tr></thead>
        <tbody>
            @forelse($pedidos as $pedido)
                <tr>
                    <td>{{ $pedido->numero }}</td>
                    <td>{{ $pedido->fecha_pedido?->format('d/m/Y') }}</td>
                    <td>{{ $pedido->cliente_nombre }}</td>
                    <td><span class="seg-estado">{{ $estados[$pedido->estado] ?? ucfirst(str_replace('_',' ',$pedido->estado)) }}</span></td>
                    <td>S/ {{ number_format((float) $pedido->total, 2) }}</td>
                    <td><a class="seg-enlace" href="{{ route('seguimiento.show', $pedido) }}">Ver detalle</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="seg-vacio">No se encontraron pedidos para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div style="margin-top:15px">{{ $pedidos->links() }}</div>
</section>
@endsection
