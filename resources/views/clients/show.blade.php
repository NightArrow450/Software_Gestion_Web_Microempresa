
@extends('layouts.app')

@section('title', 'Detalle del Cliente')
@section('page-title', 'Detalle del Cliente')
@section('breadcrumb', 'Inicio / Clientes / Detalle')

@section('content')
@php
    $permisos = auth()->user()->role?->permisos?->pluck('nombre')->all() ?? [];
    $puedeEditar = in_array('clientes.editar', $permisos, true);
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">Ficha del cliente</div>
        <h1>{{ $cliente->nombre }}</h1>
        <p>Información registrada del cliente.</p>
    </div>

    <div style="display:flex; gap:10px;">
        <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
            Volver
        </a>

        @if($puedeEditar)
            <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-primary">
                <span class="material-symbols-outlined">edit</span>
                Editar
            </a>
        @endif
    </div>
</div>

<section class="form-section">
    <h2 class="form-section-title">Datos del cliente</h2>

    <div class="details-grid">
        @foreach([
            'Nombre o razón social' => $cliente->nombre,
            'RUC / DNI' => $cliente->documento,
            'Teléfono' => $cliente->telefono,
            'Correo electrónico' => $cliente->correo,
            'Mercado' => $cliente->mercado,
            'Puesto / Referencia' => $cliente->puesto_referencia,
            'Distrito' => $cliente->distrito,
            'Dirección' => $cliente->direccion,
            'Estado' => $cliente->estado ? 'Activo' : 'Inactivo',
        ] as $etiqueta => $valor)
            <div>
                <div class="detail-label">{{ $etiqueta }}</div>
                <div class="detail-value">{{ $valor ?: '—' }}</div>
            </div>
        @endforeach
    </div>
</section>
@endsection
