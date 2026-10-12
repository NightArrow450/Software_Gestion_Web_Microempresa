@extends('layouts.app')

@section('title', 'Gestión de Pedidos')
@section('page-title', 'Gestión de Pedidos')
@section('breadcrumb', 'Inicio / Pedidos')

@section('content')
@php
    $puedeCrear = auth()->user()->role?->tienePermiso('pedidos.crear') ?? false;
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">Operaciones comerciales</div>
        <h1>Gestión de Pedidos</h1>
        <p>Consulta las notas registradas y sus fechas de entrega.</p>
    </div>

    @if($puedeCrear)
        <a href="{{ route('pedidos.create') }}" class="btn btn-primary">
            <span class="material-symbols-outlined">add</span>
            Nuevo pedido
        </a>
    @endif
</div>

<section class="filters">
    <form method="GET" action="{{ route('pedidos.index') }}">
        <div class="filters-grid">
            <div class="form-group">
                <label class="form-label" for="search">
                    Cliente o número
                </label>

                <input
                    class="form-control"
                    name="search"
                    id="search"
                    value="{{ request('search') }}"
                    placeholder="Cliente o N.º 000001"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="fecha_entrega">
                    Fecha de entrega
                </label>

                <input
                    type="date"
                    class="form-control"
                    id="fecha_entrega"
                    name="fecha_entrega"
                    value="{{ request('fecha_entrega') }}"
                >
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:14px;">
            <button class="btn btn-primary" type="submit">
                Buscar
            </button>

            <a
                class="btn btn-secondary"
                href="{{ route('pedidos.index') }}"
            >
                Limpiar
            </a>
        </div>
    </form>
</section>

<section class="form-section">
    <h2 class="form-section-title">
        Imprimir dos notas en una hoja A4 horizontal
    </h2>

    <form
        action="{{ route('pedidos.imprimir-dos') }}"
        method="GET"
        target="_blank"
    >
        <div class="form-grid">
            @foreach([1, 2] as $posicion)
                <div class="form-group">
                    <label
                        class="form-label"
                        for="pedido_{{ $posicion }}"
                    >
                        Pedido {{ $posicion }}
                    </label>

                    <select
                        name="pedido_{{ $posicion }}"
                        id="pedido_{{ $posicion }}"
                        class="form-control"
                        required
                    >
                        <option value="">
                            Seleccionar pedido
                        </option>

                        @foreach($opcionesImpresion as $opcion)
                            <option value="{{ $opcion->id }}">
                                N.º {{ str_pad((string) $opcion->id, 6, '0', STR_PAD_LEFT) }}
                                — {{ $opcion->cliente_nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>

        <button
            type="submit"
            class="btn btn-secondary"
            style="margin-top:12px;"
            @disabled($opcionesImpresion->count() < 2)
        >
            <span class="material-symbols-outlined">print</span>
            Preparar impresión
        </button>

        @if($errors->any())
            <div style="color:#b3261e; margin-top:8px;">
                {{ $errors->first() }}
            </div>
        @endif
    </form>
</section>

<section class="table-card" style="margin-top:22px;">
    <div class="table-header">
        <strong>Pedidos registrados</strong>

        <span class="muted">
            {{ $pedidos->total() }} resultado(s)
        </span>
    </div>

    <div class="table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>N.º</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Fecha de pedido</th>
                    <th>Entrega</th>
                    <th>Estado</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($pedidos as $pedido)
                    <tr>
                        <td>
                            <strong>{{ $pedido->numero }}</strong>
                        </td>

                        <td>
                            {{ $pedido->cliente_nombre }}
                        </td>

                        <td>
                            {{ $pedido->vendedor?->first_name }}
                            {{ $pedido->vendedor?->last_name }}
                        </td>

                        <td>
                            {{ $pedido->fecha_pedido->format('d/m/Y') }}
                        </td>

                        <td>
                            {{ $pedido->fecha_entrega->format('d/m/Y') }}
                        </td>

                        <td>
                            <span class="role-badge">
                                {{ ucfirst($pedido->estado) }}
                            </span>
                        </td>

                        <td>
                            S/ {{ number_format((float) $pedido->total, 2) }}
                        </td>

                        <td>
                            <a
                                class="icon-button"
                                href="{{ route('pedidos.show', $pedido) }}"
                                title="Ver pedido"
                            >
                                <span class="material-symbols-outlined">
                                    visibility
                                </span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="8"
                            style="text-align:center;padding:32px;"
                        >
                            Todavía no hay pedidos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pedidos->hasPages())
        <div style="padding:18px;">
            {{ $pedidos->links() }}
        </div>
    @endif
</section>
@endsection
