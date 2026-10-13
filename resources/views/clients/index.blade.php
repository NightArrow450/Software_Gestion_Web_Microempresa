@extends('layouts.app')

@section('title', 'Gestión de Clientes')
@section('page-title', 'Gestión de Clientes')
@section('breadcrumb', 'Inicio / Clientes')

@section('content')
@php
    $permisos = auth()->user()->role?->permisos?->pluck('nombre')->all() ?? [];

    $puedeCrear = in_array('clientes.crear', $permisos, true);
    $puedeEditar = in_array('clientes.editar', $permisos, true);
    $puedeCambiarEstado = in_array('clientes.cambiar_estado', $permisos, true);
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">Operaciones comerciales</div>
        <h1>Gestión de Clientes</h1>
        <p>Administra los clientes que realizan pedidos.</p>
    </div>

    @if($puedeCrear)
        <a href="{{ route('clientes.create') }}" class="btn btn-primary">
            <span class="material-symbols-outlined">person_add</span>
            Nuevo cliente
        </a>
    @endif
</div>

<section class="filters">
    <form method="GET" action="{{ route('clientes.index') }}">
        <div class="filters-grid">
            <div class="form-group">
                <label for="search" class="form-label">Buscar cliente</label>
                <input
                    type="text"
                    name="search"
                    id="search"
                    class="form-control"
                    value="{{ request('search') }}"
                    placeholder="Nombre, documento, teléfono o mercado"
                >
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Estado</label>
                <select name="status" id="status" class="form-control">
                    <option value="">Todos</option>
                    <option value="1" @selected(request('status') === '1')>Activos</option>
                    <option value="0" @selected(request('status') === '0')>Inactivos</option>
                </select>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:15px;">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined">search</span>
                Buscar
            </button>

            <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
                Limpiar
            </a>
        </div>
    </form>
</section>

<section class="table-card">
    <div class="table-header">
        <strong>Clientes registrados</strong>
        <div class="muted">{{ $clientes->total() }} resultado(s)</div>
    </div>

    <div class="table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Teléfono</th>
                    <th>Mercado</th>
                    <th>Estado</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $cliente)
                    <tr>
                        <td>
                            <strong>{{ $cliente->nombre }}</strong>
                            <div class="muted">{{ $cliente->distrito ?: '—' }}</div>
                        </td>

                        <td>{{ $cliente->documento ?: '—' }}</td>
                        <td>{{ $cliente->telefono ?: '—' }}</td>
                        <td>{{ $cliente->mercado ?: '—' }}</td>

                        <td>
                            <span class="status-badge {{ $cliente->estado ? 'status-active' : 'status-inactive' }}">
                                {{ $cliente->estado ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>

                        <td>
                            <div class="table-actions">
                                <a
                                    href="{{ route('clientes.show', $cliente) }}"
                                    class="icon-button"
                                    title="Ver cliente"
                                >
                                    <span class="material-symbols-outlined">visibility</span>
                                </a>

                                @if($puedeEditar)
                                    <a
                                        href="{{ route('clientes.edit', $cliente) }}"
                                        class="icon-button"
                                        title="Editar cliente"
                                    >
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>
                                @endif

                                @if($puedeCambiarEstado)
                                    <form
                                        method="POST"
                                        action="{{ route('clientes.cambiar-estado', $cliente) }}"
                                        onsubmit="return confirm('¿Cambiar el estado de este cliente?')"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="icon-button"
                                            title="Cambiar estado"
                                        >
                                            <span class="material-symbols-outlined">
                                                {{ $cliente->estado ? 'block' : 'check_circle' }}
                                            </span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:35px;">
                            No se encontraron clientes.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($clientes->hasPages())
        <div style="padding:20px;">
            {{ $clientes->links() }}
        </div>
    @endif
</section>
@endsection
