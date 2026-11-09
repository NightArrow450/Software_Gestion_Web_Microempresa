@extends('layouts.app')

@section('title', 'Detalle de Usuario')
@section('page-title', 'Detalle de Usuario')
@section('breadcrumb', 'Inicio / Usuarios / Detalle')

@section('content')

<div class="page-heading">
    <div>
        <div class="eyebrow">Gestión de usuarios</div>
        <h1>
            {{ $user->first_name }} {{ $user->last_name }}
        </h1>
        <p>Consulta la información y el estado de la cuenta.</p>
    </div>

    <div
        style="
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        "
    >
        <a
            href="{{ route('usuarios.index') }}"
            class="btn btn-secondary"
        >
            <span class="material-symbols-outlined">arrow_back</span>
            Volver
        </a>

        <a
            href="{{ route('usuarios.edit', $user) }}"
            class="btn btn-primary"
        >
            <span class="material-symbols-outlined">edit</span>
            Editar Usuario
        </a>
    </div>
</div>

<section class="form-section">
    <h2 class="form-section-title">Información del usuario</h2>

    <div class="details-grid">

        <div>
            <div class="detail-label">Nombres</div>
            <div class="detail-value">{{ $user->first_name }}</div>
        </div>

        <div>
            <div class="detail-label">Apellidos</div>
            <div class="detail-value">{{ $user->last_name }}</div>
        </div>

        <div>
            <div class="detail-label">Correo electrónico</div>
            <div class="detail-value">{{ $user->email }}</div>
        </div>

        <div>
            <div class="detail-label">Rol</div>
            <div class="detail-value">
                <span class="role-badge">
                    {{ $user->role?->nombre ?? 'Sin rol' }}
                </span>
            </div>
        </div>

        <div>
            <div class="detail-label">Estado</div>
            <div class="detail-value">
                @if($user->status)
                    <span class="status-badge status-active">● Activo</span>
                @else
                    <span class="status-badge status-inactive">● Inactivo</span>
                @endif
            </div>
        </div>

        <div>
            <div class="detail-label">Fecha de registro</div>
            <div class="detail-value">
                {{ $user->created_at?->format('d/m/Y H:i') }}
            </div>
        </div>

        <div>
            <div class="detail-label">Última actualización</div>
            <div class="detail-value">
                {{ $user->updated_at?->format('d/m/Y H:i') }}
            </div>
        </div>

        <div>
            <div class="detail-label">Identificador</div>
            <div class="detail-value">#{{ $user->id }}</div>
        </div>

    </div>
</section>

@if(auth()->id() !== $user->id)
    <section class="form-section">
        <h2 class="form-section-title">Estado de la cuenta</h2>

        <p class="muted" style="margin-top:-10px; margin-bottom:18px;">
            {{ $user->status
                ? 'Puedes desactivar temporalmente el acceso de este usuario al sistema.'
                : 'Puedes volver a habilitar el acceso de este usuario al sistema.'
            }}
        </p>

        <form
            method="POST"
            action="{{ route('usuarios.cambiar-estado', $user) }}"
            onsubmit="return confirm('{{ $user->status
                ? '¿Deseas desactivar este usuario?'
                : '¿Deseas activar este usuario?'
            }}')"
        >
            @csrf
            @method('PATCH')

            <button
                type="submit"
                class="{{ $user->status ? 'btn btn-danger' : 'btn btn-primary' }}"
            >
                <span class="material-symbols-outlined">
                    {{ $user->status ? 'block' : 'check_circle' }}
                </span>

                {{ $user->status ? 'Desactivar Usuario' : 'Activar Usuario' }}
            </button>
        </form>
    </section>
@else
    <section class="form-section">
        <h2 class="form-section-title">Estado de la cuenta</h2>

        <div class="muted">
            Esta es tu cuenta actual. Por seguridad no puedes desactivarla mientras estás utilizando el sistema.
        </div>
    </section>
@endif

@endsection
