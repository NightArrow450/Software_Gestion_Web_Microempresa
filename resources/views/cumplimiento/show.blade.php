@extends('layouts.app')

@section('title', 'Cumplimiento de Pedido')
@section('page-title', 'Cumplimiento de Pedido')
@section('breadcrumb', 'Inicio / Cumplimiento / Detalle')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint4.css') }}">@endpush

@section('content')
    <div class="cumplimiento-encabezado">
        <div>
            <div class="eyebrow">Cumplimiento de pedidos</div>
            <h1>Pedido N.º {{ $pedido->numero }}</h1>
            <p>Cliente: <strong>{{ $pedido->cliente_nombre }}</strong> · Estado:
                <span class="cumplimiento-estado">{{ ucfirst(str_replace('_', ' ', $pedido->estado)) }}</span>
            </p>
        </div>
        <div class="s2-actions">
            <a class="btn btn-secondary" href="{{ route('cumplimiento.index') }}">Volver a cumplimiento</a>
            <a class="btn btn-secondary" href="{{ route('cumplimiento.lista-preparacion') }}">Ver lista general de preparación</a>
            <a class="btn btn-secondary" href="{{ route('pedidos.show', $pedido) }}">Ver pedido original</a>
        </div>
    </div>

    @if(session('success'))
        <div class="cumplimiento-nota" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="cumplimiento-tarjeta" style="border-color:#dc2626;" role="alert">
            @foreach($errors->all() as $error)<p style="color:#b91c1c">{{ $error }}</p>@endforeach
        </div>
    @endif

    <section class="cumplimiento-tarjeta">
        <h2 class="form-section-title">Acciones de cumplimiento</h2>
        @include('cumplimiento.partials.acciones')
    </section>

    <section class="cumplimiento-tarjeta">
        <h2 class="form-section-title">Productos solicitados</h2>
        <div class="table-container">
            <table class="cumplimiento-tabla">
                <thead><tr><th>SKU</th><th>Producto</th><th>Modalidad</th><th>Cantidad</th></tr></thead>
                <tbody>
                @foreach($pedido->detalles as $detalle)
                    <tr>
                        <td>{{ $detalle->codigo_sku }}</td>
                        <td>{{ $detalle->descripcion_producto }}</td>
                        <td>{{ ucfirst($detalle->modalidad) }}</td>
                        <td>{{ $detalle->cantidad }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="cumplimiento-tarjeta">
        <h2 class="form-section-title">Historial de estados</h2>
        <div class="table-container">
            <table class="cumplimiento-tabla">
                <thead><tr><th>Fecha</th><th>Anterior</th><th>Nuevo</th><th>Usuario</th><th>Motivo</th></tr></thead>
                <tbody>
                @foreach($pedido->historialEstados as $evento)
                    <tr>
                        <td>{{ $evento->created_at?->timezone('America/Lima')->format('d/m/Y H:i') }}</td>
                        <td>{{ $evento->estado_anterior ?? '—' }}</td>
                        <td>{{ ucfirst(str_replace('_',' ',$evento->estado_nuevo)) }}</td>
                        <td>{{ $evento->usuario?->first_name }} {{ $evento->usuario?->last_name }}</td>
                        <td>{{ $evento->motivo ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
