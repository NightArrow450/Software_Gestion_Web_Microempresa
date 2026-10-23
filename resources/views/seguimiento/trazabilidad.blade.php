@extends('layouts.app')
@section('title', 'Trazabilidad del pedido')
@section('page-title', 'Seguimiento de pedidos')
@section('breadcrumb', 'Inicio / Seguimiento / Trazabilidad')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint5.css') }}">@endpush
@section('content')
<div class="seg-top">
    <div>
        <div class="eyebrow">Sprint 5 · HU-11</div>
        <h1>Trazabilidad del pedido N.º {{ $pedido->numero }}</h1>
        <p class="seg-ayuda">Cliente: {{ $pedido->cliente_nombre }} · Estado actual: {{ ucfirst(str_replace('_',' ',$pedido->estado)) }}</p>
    </div>
    <a href="{{ route('seguimiento.show', $pedido) }}" class="btn btn-secondary">Ver estado actual</a>
</div>
<section class="seg-panel">
    <h2>Recorrido cronológico</h2>
    <p class="seg-ayuda">El registro de eventos es de solo lectura; se conserva el historial de preparación, despacho y entrega cuando exista.</p>
    <ol class="seg-timeline">
        @forelse($pedido->historialEstados as $evento)
            <li class="seg-evento">
                <h3>{{ ucfirst(str_replace('_', ' ', $evento->estado_nuevo)) }}</h3>
                <p><strong>Fecha y hora:</strong> {{ $evento->created_at?->format('d/m/Y H:i:s') ?? 'Sin fecha' }}</p>
                <p><strong>Transición:</strong> {{ $evento->estado_anterior ? ucfirst(str_replace('_', ' ', $evento->estado_anterior)) : 'Inicio' }} → {{ ucfirst(str_replace('_', ' ', $evento->estado_nuevo)) }}</p>
                <p><strong>Responsable:</strong> {{ trim(($evento->usuario?->first_name ?? '').' '.($evento->usuario?->last_name ?? '')) ?: 'Usuario no disponible' }}</p>
                @if($evento->motivo)
                    <p><strong>Motivo / observación:</strong> {{ $evento->motivo }}</p>
                @endif
            </li>
        @empty
            <li class="seg-vacio">Aún no se registraron eventos para este pedido.</li>
        @endforelse
    </ol>
</section>

@if($resumenEntrega !== null && in_array($pedido->estado, \App\Services\ControlEntregaPedido::ESTADOS, true))
    @include('cumplimiento.partials.incidencia-resumen')
@endif
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('css/entregas.css') }}">@endpush
