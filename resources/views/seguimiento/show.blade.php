@extends('layouts.app')
@section('title', 'Estado del pedido')
@section('page-title', 'Seguimiento de pedidos')
@section('breadcrumb', 'Inicio / Seguimiento / Detalle')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint5.css') }}">@endpush
@section('content')
<div class="seg-top">
    <div>
        <div class="eyebrow">Seguimiento · HU-10</div>
        <h1>Estado del pedido N.º {{ $pedido->numero }}</h1>
        <p class="seg-ayuda">Consulta informativa. Los cambios de estado se registran desde los módulos autorizados.</p>
    </div>
    <a href="{{ route('seguimiento.index') }}" class="btn btn-secondary">Volver a la búsqueda</a>
</div>
<section class="seg-panel">
    <div class="seg-top"><h2 style="margin:0">Estado actual</h2>
        <span class="seg-estado">{{ ucfirst(str_replace('_', ' ', $pedido->estado)) }}</span>
    </div>
    <div class="seg-detalles">
        <div class="seg-dato"><b>Cliente</b><span>{{ $pedido->cliente_nombre }}</span></div>
        <div class="seg-dato"><b>Fecha del pedido</b><span>{{ $pedido->fecha_pedido?->format('d/m/Y') }}</span></div>
        <div class="seg-dato"><b>Entrega prevista</b><span>{{ $pedido->fecha_entrega?->format('d/m/Y') }}</span></div>
        <div class="seg-dato"><b>Condición de pago</b><span>{{ ucfirst($pedido->condicion_pago) }}</span></div>
        <div class="seg-dato"><b>Total registrado</b><span>S/ {{ number_format((float) $pedido->total, 2) }}</span></div>
        <div class="seg-dato"><b>Registrado en sistema</b><span>{{ $pedido->created_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
    </div>
</section>
<section class="seg-panel">
    <h2>Fechas relevantes</h2>
    <div class="seg-detalles">
        @foreach([
            'Procesamiento comercial' => $pedido->procesado_en,
            'Preparación iniciada' => $pedido->preparacion_iniciada_en,
            'Preparación finalizada' => $pedido->preparacion_finalizada_en,
            'Despacho' => $pedido->despachado_en,
            'Entrega' => $pedido->entregado_en,
        ] as $etiqueta => $fecha)
            <div class="seg-dato"><b>{{ $etiqueta }}</b>
                <span>{{ $fecha ? \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y H:i') : 'Pendiente' }}</span>
            </div>
        @endforeach
    </div>
</section>
<section class="seg-panel">
    <h2>Recorrido del pedido</h2>
    <p class="seg-ayuda">Consulta cronológica de estados, responsables y motivos registrados.</p>
    <a class="btn btn-primary" href="{{ route('seguimiento.trazabilidad', $pedido) }}">Ver trazabilidad completa</a>
</section>

@if($resumenEntrega !== null && in_array($pedido->estado, \App\Services\ControlEntregaPedido::ESTADOS, true))
    @include('cumplimiento.partials.incidencia-resumen')
@endif
@include('orders.partials.composiciones', ['soloLectura' => true])
@include('orders.partials.versiones')
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('css/entregas.css') }}">@endpush
