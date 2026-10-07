@extends('layouts.app')

@section('title', 'Gestión de Usuarios')
@section('page-title', 'Gestión de Usuarios')
@section('breadcrumb', 'Inicio / Usuarios')

@section('content')

<div class="page-heading">
    <div>
        <div class="eyebrow">Administración</div>
        <h1>Gestión de Usuarios</h1>
        <p>Administra las cuentas, roles y estados de los usuarios del sistema.</p>
    </div>

    <a
        href="{{ route('usuarios.create') }}"
        class="btn btn-primary"
    >
        <span class="material-symbols-outlined">person_add</span>
        Nuevo Usuario
    </a>
</div>

{{-- FILTROS --}}
<section class="filters">
    <form
        method="GET"
        action="{{ route('usuarios.index') }}"
    >
        <div class="filters-grid">

            <div class="form-group">
                <label for="search" class="form-label">Buscar usuario</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Nombre, apellido o correo..."
                    class="form-control"
                >
            </div>

            <div class="form-group">
                <label for="role" class="form-label">Rol</label>
                <select
                    id="role"
                    name="role"
                    class="form-control"
                >
                    <option value="">Todos</option>

                    @foreach($roles as $role)
                        <option
                            value="{{ $role->id }}"
                            {{ (string) request('role') === (string) $role->id ? 'selected' : '' }}
                        >
                            {{ $role->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Estado</label>
                <select
                    id="status"
                    name="status"
                    class="form-control"
                >
                    <option value="">Todos</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>
                        Activo
                    </option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>
                        Inactivo
                    </option>
                </select>
            </div>

        </div>

        <div
            style="
                display:flex;
                gap:10px;
                margin-top:15px;
            "
        >
            <button
                type="submit"
                class="btn btn-primary"
            >
                <span class="material-symbols-outlined">filter_alt</span>
                Aplicar filtros
            </button>

            <a
                href="{{ route('usuarios.index') }}"
                class="btn btn-secondary"
            >
                Limpiar
            </a>
        </div>
    </form>
</section>

{{-- TABLA --}}
<section class="table-card">

    <div class="table-header">
        <strong>Usuarios registrados</strong>
        <div class="muted" style="margin-top:4px;">
            {{ $users->total() }} resultado(s)
        </div>
    </div>

    @if($users->count())

        <div class="table-container">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>
                                <strong>
                                    {{ $user->first_name }} {{ $user->last_name }}
                                </strong>

                                @if(auth()->id() === $user->id)
                                    <div
                                        style="
                                            margin-top:3px;
                                            color:var(--primary);
                                            font-size:11px;
                                            font-weight:700;
                                        "
                                    >
                                        Tu cuenta
                                    </div>
                                @endif
                            </td>

                            <td>{{ $user->email }}</td>

                            <td>
                                <span class="role-badge">
                                    {{ $user->role?->nombre ?? 'Sin rol' }}
                                </span>
                            </td>

                            <td>
                                @if($user->status)
                                    <span class="status-badge status-active">
                                        ● Activo
                                    </span>
                                @else
                                    <span class="status-badge status-inactive">
                                        ● Inactivo
                                    </span>
                                @endif
                            </td>

                            <td>
                                {{ $user->created_at?->format('d/m/Y') }}
                            </td>

                            <td>
                                <div class="table-actions">

                                    <a
                                        href="{{ route('usuarios.show', $user) }}"
                                        class="icon-button"
                                        title="Ver usuario"
                                    >
                                        <span class="material-symbols-outlined">visibility</span>
                                    </a>

                                    <a
                                        href="{{ route('usuarios.edit', $user) }}"
                                        class="icon-button"
                                        title="Editar usuario"
                                    >
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>

                                    @if(auth()->id() !== $user->id)
                                        <form
                                            method="POST"
                                            action="{{ route('usuarios.cambiar-estado', $user) }}"
                                            onsubmit="return confirm('{{ $user->status
                                                ? '¿Deseas desactivar este usuario?'
                                                : '¿Deseas activar este usuario?'
                                            }}')"
                                            style="display:inline;"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <button
                                                type="submit"
                                                class="icon-button"
                                                title="{{ $user->status ? 'Desactivar usuario' : 'Activar usuario' }}"
                                            >
                                                <span class="material-symbols-outlined">
                                                    {{ $user->status ? 'block' : 'check_circle' }}
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

        @if($users->hasPages())
            <div style="padding:20px;">
                {{ $users->links() }}
            </div>
        @endif

    @else

        <div
            style="
                padding:60px 20px;
                text-align:center;
            "
        >
            <span
                class="material-symbols-outlined"
                style="
                    font-size:48px;
                    color:var(--primary);
                "
            >
                person_search
            </span>

            <h3 style="margin:12px 0 0;">
                No se encontraron usuarios
            </h3>

            <p class="muted" style="margin-top:6px;">
                Modifica los filtros o registra un nuevo usuario.
            </p>
        </div>

    @endif

</section>

@endsection
