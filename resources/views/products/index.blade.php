@extends('layouts.app')

@section('title', 'Gestión de Productos')
@section('page-title', 'Gestión de Productos')
@section('breadcrumb', 'Inicio / Productos')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/products.css') }}?v=1">
@endpush

@section('content')
@php
    $permisosRol = auth()->user()->role?->permisos()->pluck('nombre')->all() ?? [];
    $puedeCrear = in_array('productos.crear', $permisosRol, true);
    $puedeEditar = in_array('productos.editar', $permisosRol, true);
    $puedeCambiarEstado = in_array('productos.cambiar_estado', $permisosRol, true);
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">Catálogo comercial</div>
        <h1>Gestión de Productos</h1>
        <p>Administra los productos, variantes y presentaciones disponibles para la venta.</p>
    </div>

    <div class="product-actions">
        @if($puedeEditar)
            <a href="{{ route('categorias.index') }}" class="btn btn-secondary">
                <span class="material-symbols-outlined">category</span>
                Categorías
            </a>
        @endif

        @if($puedeCrear)
            <a href="{{ route('productos.create') }}" class="btn btn-primary">
                <span class="material-symbols-outlined">add</span>
                Nuevo producto
            </a>
        @endif
    </div>
</div>

<section class="filters">
    <form method="GET" action="{{ route('productos.index') }}">
        <div class="filters-grid">
            <div class="form-group">
                <label for="search" class="form-label">Buscar producto</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control"
                    placeholder="Código, producto o marca..."
                >
            </div>

            <div class="form-group">
                <label for="categoria" class="form-label">Categoría</label>
                <select id="categoria" name="categoria" class="form-control">
                    <option value="">Todas</option>
                    @foreach($categorias as $categoria)
                        <option
                            value="{{ $categoria->id }}"
                            {{ (string) request('categoria') === (string) $categoria->id ? 'selected' : '' }}
                        >
                            {{ $categoria->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Estado</label>
                <select id="status" name="status" class="form-control">
                    <option value="">Todos</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Activo</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:15px;">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined">filter_alt</span>
                Aplicar filtros
            </button>

            <a href="{{ route('productos.index') }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </form>
</section>

<section class="table-card">
    <div class="table-header">
        <strong>Productos registrados</strong>
        <div class="muted" style="margin-top:4px;">{{ $productos->total() }} resultado(s)</div>
    </div>

    @if($productos->count())
        <div class="table-container">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Marca</th>
                        <th>Categoría</th>
                        <th>Presentaciones</th>
                        <th>Estado</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($productos as $producto)
                        <tr>
                            <td>
                                @if($producto->imagen_referencia)
                                    <img
                                        src="{{ asset('storage/'.$producto->imagen_referencia) }}"
                                        alt="{{ $producto->nombre }}"
                                        class="product-thumb"
                                    >
                                @else
                                    <div class="product-thumb placeholder">
                                        <span class="material-symbols-outlined">inventory_2</span>
                                    </div>
                                @endif
                            </td>

                            <td><strong>{{ $producto->codigo }}</strong></td>

                            <td>
                                <strong>{{ $producto->nombre }}</strong>
                                @if($producto->variantes_count)
                                    <div class="muted" style="margin-top:3px;">
                                        {{ $producto->variantes_count }} variante(s)
                                    </div>
                                @endif
                            </td>

                            <td>{{ $producto->marca ?: '—' }}</td>
                            <td>{{ $producto->categoria?->nombre ?? 'Sin categoría' }}</td>

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

                                    @if($puedeEditar)
                                        <a
                                            href="{{ route('productos.edit', $producto) }}"
                                            class="icon-button"
                                            title="Editar producto"
                                        >
                                            <span class="material-symbols-outlined">edit</span>
                                        </a>
                                    @endif

                                    @if($puedeCambiarEstado)
                                        <form
                                            method="POST"
                                            action="{{ route('productos.cambiar-estado', $producto) }}"
                                            onsubmit="return confirm('{{ $producto->estado ? '¿Deseas desactivar este producto?' : '¿Deseas activar este producto?' }}')"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <button
                                                type="submit"
                                                class="icon-button"
                                                title="{{ $producto->estado ? 'Desactivar producto' : 'Activar producto' }}"
                                            >
                                                <span class="material-symbols-outlined">
                                                    {{ $producto->estado ? 'block' : 'check_circle' }}
                                                </span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($productos->hasPages())
            <div style="padding:20px;">{{ $productos->links() }}</div>
        @endif
    @else
        <div style="padding:60px 20px; text-align:center;">
            <span class="material-symbols-outlined" style="font-size:48px; color:var(--primary);">inventory_2</span>
            <h3 style="margin:12px 0 0;">No se encontraron productos</h3>
            <p class="muted" style="margin-top:6px;">Modifica los filtros o registra un nuevo producto.</p>

            @if($puedeCrear)
                <a href="{{ route('productos.create') }}" class="btn btn-primary" style="margin-top:16px;">
                    Registrar producto
                </a>
            @endif
        </div>
    @endif
</section>
@endsection
