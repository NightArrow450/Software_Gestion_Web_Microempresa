@extends('layouts.app')

@section('title', 'Detalle de Categoría')
@section('page-title', 'Detalle de Categoría')
@section('breadcrumb', 'Inicio / Productos / Categorías / Detalle')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/products.css') }}?v=1">
@endpush

@section('content')
@php
    $puedeEditar = auth()->user()->role?->tienePermiso('productos.editar') ?? false;
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">Catálogo comercial</div>
        <h1>{{ $categoria->nombre }}</h1>
        <p>Consulta la información de la categoría y los productos asociados.</p>
    </div>

    <div class="product-actions">
        <a href="{{ route('categorias.index') }}" class="btn btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            Volver
        </a>

        @if($puedeEditar)
            <a href="{{ route('categorias.edit', $categoria) }}" class="btn btn-primary">
                <span class="material-symbols-outlined">edit</span>
                Editar categoría
            </a>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:22px;">
        <span class="material-symbols-outlined">check_circle</span>
        <div>{{ session('success') }}</div>
    </div>
@endif

<section class="form-section">
    <h2 class="form-section-title">Información de la categoría</h2>

    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">Código</label>
            <div><strong>{{ $categoria->codigo ?: 'Sin código' }}</strong></div>
        </div>

        <div class="form-group">
            <label class="form-label">Estado</label>
            <div>
                @if($categoria->estado)
                    <span class="status-badge status-active">● Activa</span>
                @else
                    <span class="status-badge status-inactive">● Inactiva</span>
                @endif
            </div>
        </div>
    </div>

    <div class="form-group" style="margin-top:20px;">
        <label class="form-label">Descripción</label>
        <div class="muted" style="line-height:1.6;">
            {{ $categoria->descripcion ?: 'Sin descripción registrada.' }}
        </div>
    </div>

    <div class="form-group" style="margin-top:20px;">
        <label class="form-label">Productos asociados</label>
        <div>
            <span class="role-badge">
                {{ $categoria->productos_count }} producto(s)
            </span>
        </div>
    </div>
</section>

<section class="table-card" style="margin-top:22px;">
    <div class="table-header">
        <strong>Productos de esta categoría</strong>
        <div class="muted" style="margin-top:4px;">
            Productos actualmente asociados a {{ $categoria->nombre }}
        </div>
    </div>

    <div class="table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Marca</th>
                    <th>Presentaciones</th>
                    <th>Estado</th>
                    <th style="text-align:right;">Acción</th>
                </tr>
            </thead>

            <tbody>
                @forelse($productos as $producto)
                    <tr>
                        <td><strong>{{ $producto->codigo }}</strong></td>
                        <td>{{ $producto->nombre }}</td>
                        <td>{{ $producto->marca ?: '—' }}</td>
                        <td>
                            <span class="role-badge">
                                {{ $producto->presentaciones_count }} presentación(es)
                            </span>
                        </td>
                        <td>
                            @if($producto->estado)
                                <span class="status-badge status-active">● Activo</span>
                            @else
                                <span class="status-badge status-inactive">● Inactivo</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a
                                    href="{{ route('productos.show', $producto) }}"
                                    class="icon-button"
                                    title="Ver producto"
                                >
                                    <span class="material-symbols-outlined">visibility</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px;">
                            Esta categoría todavía no tiene productos asociados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
