@extends('layouts.app')

@section('title', 'Categorías de Productos')
@section('page-title', 'Categorías de Productos')
@section('breadcrumb', 'Inicio / Productos / Categorías')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/products.css') }}?v=1">
@endpush

@section('content')
@php
    $puedeEditar = auth()->user()->role?->tienePermiso('productos.editar') ?? false;
    $puedeCambiarEstado = auth()->user()->role?->tienePermiso('productos.cambiar_estado') ?? false;
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">Catálogo comercial</div>
        <h1>Categorías de Productos</h1>
        <p>Administra la clasificación utilizada para organizar el catálogo.</p>
    </div>

    <div class="product-actions">
        <a href="{{ route('productos.index') }}" class="btn btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            Productos
        </a>

        @if($puedeEditar)
            <a href="{{ route('categorias.create') }}" class="btn btn-primary">
                <span class="material-symbols-outlined">add</span>
                Nueva categoría
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

<section class="table-card">
    <div class="table-header">
        <strong>Categorías registradas</strong>
        <div class="muted" style="margin-top:4px;">
            {{ $categorias->count() }} categoría(s)
        </div>
    </div>

    <div class="table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Categoría</th>
                    <th>Descripción</th>
                    <th>Productos</th>
                    <th>Estado</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($categorias as $categoria)
                    <tr>
                        <td>
                            <span class="role-badge">
                                {{ $categoria->codigo ?: 'Sin código' }}
                            </span>
                        </td>

                        <td>
                            <strong>{{ $categoria->nombre }}</strong>
                        </td>

                        <td>
                            {{ $categoria->descripcion ?: '—' }}
                        </td>

                        <td>
                            <span class="role-badge">
                                {{ $categoria->productos_count }} producto(s)
                            </span>
                        </td>

                        <td>
                            @if($categoria->estado)
                                <span class="status-badge status-active">● Activa</span>
                            @else
                                <span class="status-badge status-inactive">● Inactiva</span>
                            @endif
                        </td>

                        <td>
                            <div class="table-actions">
                                <a
                                    href="{{ route('categorias.show', $categoria) }}"
                                    class="icon-button"
                                    title="Ver categoría"
                                >
                                    <span class="material-symbols-outlined">visibility</span>
                                </a>

                                @if($puedeEditar)
                                    <a
                                        href="{{ route('categorias.edit', $categoria) }}"
                                        class="icon-button"
                                        title="Editar categoría"
                                    >
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>
                                @endif

                                @if($puedeCambiarEstado)
                                    <form
                                        method="POST"
                                        action="{{ route('categorias.cambiar-estado', $categoria) }}"
                                        onsubmit="return confirm('{{ $categoria->estado ? '¿Deseas desactivar esta categoría?' : '¿Deseas activar esta categoría?' }}')"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="icon-button"
                                            title="{{ $categoria->estado ? 'Desactivar categoría' : 'Activar categoría' }}"
                                        >
                                            <span class="material-symbols-outlined">
                                                {{ $categoria->estado ? 'block' : 'check_circle' }}
                                            </span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px;">
                            No hay categorías registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
