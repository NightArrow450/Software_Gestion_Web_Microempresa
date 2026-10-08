@extends('layouts.app')

@section('title', $producto->nombre)
@section('page-title', 'Detalle del Producto')
@section('breadcrumb', 'Inicio / Productos / Detalle')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/products.css') }}?v=1">
@endpush

@section('content')
@php
    $permisosRol = auth()->user()->role?->permisos()->pluck('nombre')->all() ?? [];
    $puedeEditar = in_array('productos.editar', $permisosRol, true);
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">Detalle del producto</div>
        <h1>{{ $producto->nombre }}</h1>
        <p>{{ $producto->codigo }} · {{ $producto->marca ?: 'Sin marca' }}</p>
    </div>

    <div class="product-actions">
        <a href="{{ route('productos.index') }}" class="btn btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            Volver
        </a>

        @if($puedeEditar)
            <a href="{{ route('productos.edit', $producto) }}" class="btn btn-primary">
                <span class="material-symbols-outlined">edit</span>
                Editar producto
            </a>
        @endif
    </div>
</div>

<section class="product-detail-grid">
    <div class="card">
        @if($producto->imagen_referencia)
            <img
                src="{{ asset('storage/'.$producto->imagen_referencia) }}"
                alt="{{ $producto->nombre }}"
                class="product-main-image"
            >
        @else
            <div class="product-image-placeholder">
                <span class="material-symbols-outlined">inventory_2</span>
            </div>
        @endif
    </div>

    <div class="card">
        <h2 class="form-section-title">Información general</h2>

        <div class="info-grid">
            <div>
                <div class="detail-label">Código</div>
                <div class="detail-value">{{ $producto->codigo }}</div>
            </div>

            <div>
                <div class="detail-label">Categoría</div>
                <div class="detail-value">{{ $producto->categoria?->nombre ?? 'Sin categoría' }}</div>
            </div>

            <div>
                <div class="detail-label">Marca</div>
                <div class="detail-value">{{ $producto->marca ?: '—' }}</div>
            </div>

            <div>
                <div class="detail-label">Estado</div>
                <div class="detail-value">
                    @if($producto->estado)
                        <span class="status-badge status-active">● Activo</span>
                    @else
                        <span class="status-badge status-inactive">● Inactivo</span>
                    @endif
                </div>
            </div>
        </div>

        <div style="margin-top:24px;">
            <div class="detail-label">Descripción</div>
            <div style="margin-top:7px; line-height:1.65;">
                {{ $producto->descripcion ?: 'Sin descripción registrada.' }}
            </div>
        </div>
    </div>
</section>

<section class="form-section" style="margin-top:22px;">
    <h2 class="form-section-title">Variantes</h2>

    @if($producto->variantes->count())
        <div class="product-badges">
            @foreach($producto->variantes as $variante)
                <span class="role-badge">
                    {{ $variante->tipo }}: {{ $variante->valor }}
                    {{ $variante->estado ? '' : ' · Inactiva' }}
                </span>
            @endforeach
        </div>
    @else
        <div class="empty-inline">Este producto no posee variantes registradas.</div>
    @endif
</section>

<section class="table-card">
    <div class="table-header">
        <strong>Presentaciones comerciales</strong>
        <div class="muted" style="margin-top:4px;">
            {{ $producto->presentaciones->count() }} presentación(es)
        </div>
    </div>

    <div class="table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Variante</th>
                    <th>Envase</th>
                    <th>Contenido</th>
                    <th>Venta por unidad</th>
                    <th>Empaque</th>
                    <th>Estado</th>
                </tr>
            </thead>

            <tbody>
                @forelse($producto->presentaciones as $presentacion)
                    <tr>
                        <td><strong>{{ $presentacion->codigo_sku }}</strong></td>
                        <td>
                            {{ $presentacion->variante
                                ? $presentacion->variante->tipo.': '.$presentacion->variante->valor
                                : 'Sin variante'
                            }}
                        </td>
                        <td>{{ $presentacion->tipo_envase ?: '—' }}</td>
                        <td>{{ $presentacion->contenido + 0 }} {{ $presentacion->unidad_medida }}</td>
                        <td>
                            @if($presentacion->venta_por_unidad)
                                Sí
                                @if(!is_null($presentacion->precio_unitario))
                                    · S/ {{ number_format((float) $presentacion->precio_unitario, 2) }}
                                @endif
                            @else
                                No
                            @endif
                        </td>
                        <td>
                            @if($presentacion->venta_por_empaque)
                                {{ $presentacion->tipo_empaque }} x {{ $presentacion->unidades_por_empaque }}
                                @if(!is_null($presentacion->precio_empaque))
                                    · S/ {{ number_format((float) $presentacion->precio_empaque, 2) }}
                                @endif
                            @else
                                No
                            @endif
                        </td>
                        <td>
                            @if($presentacion->estado)
                                <span class="status-badge status-active">● Activa</span>
                            @else
                                <span class="status-badge status-inactive">● Inactiva</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:35px;">
                            No hay presentaciones registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
