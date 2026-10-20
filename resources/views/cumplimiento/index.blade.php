@extends('layouts.app')

@section('title', 'Cumplimiento de Pedidos')
@section('page-title', 'Cumplimiento de Pedidos')
@section('breadcrumb', 'Inicio / Cumplimiento de Pedidos')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint4.css') }}">@endpush

@section('content')
    <div class="cumplimiento-encabezado">
        <div>
            <div class="eyebrow">Sprint 4 · Preparación, despacho y entrega</div>
            <h1>Cumplimiento de pedidos</h1>
            <p class="muted">Consulta la etapa operativa de los pedidos confirmados.</p>
        </div>
        <div class="cumplimiento-enlaces">
            <a href="{{ route('cumplimiento.lista-preparacion') }}" class="btn btn-primary">Ver lista general de preparación</a>
            <a href="{{ route('pedidos.index') }}" class="btn btn-secondary">Ver todos los pedidos</a>
        </div>
    </div>

    <div class="cumplimiento-nota">
        La preparación organiza los pedidos sin descontar existencias.
        Al registrar el despacho se valida y descuenta el inventario de productos terminados.
        La lista general de preparación es de solo lectura.
    </div>

    <section class="cumplimiento-tarjeta">
        <form method="GET" action="{{ route('cumplimiento.index') }}" class="cumplimiento-filtros">
            <label for="filtro-estado"><strong>Estado</strong></label>
            <select id="filtro-estado" name="estado" class="form-control">
                <option value="">Todas las etapas</option>
                @foreach($estados as $opcion)
                    <option value="{{ $opcion }}" @selected($estado === $opcion)>
                        {{ ucfirst(str_replace('_', ' ', $opcion)) }}
                    </option>
                @endforeach
            </select>
            <button class="btn btn-primary" type="submit">Filtrar</button>
        </form>
    </section>

    <section class="cumplimiento-tarjeta">
        <div class="table-container">
            <table class="cumplimiento-tabla">
                <thead><tr><th>N.º</th><th>Cliente</th><th>Entrega prevista</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                @forelse($pedidos as $pedido)
                    <tr>
                        <td>{{ $pedido->numero }}</td>
                        <td>{{ $pedido->cliente_nombre }}</td>
                        <td>{{ $pedido->fecha_entrega?->format('d/m/Y') }}</td>
                        <td><span class="cumplimiento-estado">{{ ucfirst(str_replace('_',' ',$pedido->estado)) }}</span></td>
                        <td><a class="cumplimiento-link" href="{{ route('cumplimiento.show', $pedido) }}">Ver proceso</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No hay pedidos en estas etapas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px">{{ $pedidos->links() }}</div>
    </section>
@endsection
