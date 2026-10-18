@extends('layouts.app')
@section('title', 'Consultar estado de pedidos')
@section('page-title', 'Seguimiento de pedidos')
@section('breadcrumb', 'Inicio / Seguimiento / Consultar estado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint5.css') }}">@endpush
@section('content')
<div class="seg-top">
    <div>
        <div class="eyebrow">Sprint 5 · HU-10</div>
        <h1>Consultar estado de pedidos</h1>
        <p class="seg-ayuda">Busca por número (por ejemplo, 000001) o por nombre del cliente.</p>
    </div>
</div>
<section class="seg-panel">
    <form method="GET" action="{{ route('seguimiento.index') }}" class="seg-filtros">
        <div><label for="seg-codigo">Número de pedido</label>
            <input id="seg-codigo" class="form-control" name="codigo" value="{{ $codigo }}" maxlength="12" placeholder="000001" inputmode="numeric">
        </div>
        <div><label for="seg-cliente">Cliente</label>
            <input id="seg-cliente" class="form-control" name="cliente" value="{{ $cliente }}" maxlength="180" placeholder="Nombre o parte del nombre">
        </div>
        <div class="seg-botones">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <a href="{{ route('seguimiento.index') }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </form>
    @error('codigo')<p class="text-red-600">{{ $message }}</p>@enderror
    @error('cliente')<p class="text-red-600">{{ $message }}</p>@enderror
</section>
<section class="seg-panel">
    <div class="table-container">
        <table class="seg-tabla">
            <thead><tr><th>N.º</th><th>Cliente</th><th>Fecha</th><th>Entrega prevista</th><th>Estado actual</th><th>Consulta</th></tr></thead>
            <tbody>
            @forelse($pedidos as $pedido)
                <tr>
                    <td>{{ $pedido->numero }}</td>
                    <td>{{ $pedido->cliente_nombre }}</td>
                    <td>{{ $pedido->fecha_pedido?->format('d/m/Y') }}</td>
                    <td>{{ $pedido->fecha_entrega?->format('d/m/Y') }}</td>
                    <td><span class="seg-estado">{{ ucfirst(str_replace('_', ' ', $pedido->estado)) }}</span></td>
                    <td><a class="seg-enlace" href="{{ route('seguimiento.show', $pedido) }}">Ver estado</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="seg-vacio">No se encontraron pedidos con estos criterios.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:15px">{{ $pedidos->links() }}</div>
</section>
@endsection
