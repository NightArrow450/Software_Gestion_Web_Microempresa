@extends('layouts.app')

@section('title', 'Editar Usuario')
@section('page-title', 'Editar Usuario')
@section('breadcrumb', 'Inicio / Usuarios / Editar')

@section('content')

<div class="page-heading">
    <div>
        <div class="eyebrow">Gestión de usuarios</div>
        <h1>Editar Usuario</h1>
        <p>
            Actualiza la información de {{ $user->first_name }} {{ $user->last_name }}.
        </p>
    </div>

    <a
        href="{{ route('usuarios.show', $user) }}"
        class="btn btn-secondary"
    >
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>

@include('users.partials.form')

@endsection
