@extends('layouts.app')

@section('title', 'Nuevo Usuario')
@section('page-title', 'Nuevo Usuario')
@section('breadcrumb', 'Inicio / Usuarios / Nuevo')

@section('content')

<div class="page-heading">
    <div>
        <div class="eyebrow">Gestión de usuarios</div>
        <h1>Nuevo Usuario</h1>
        <p>Registra una nueva cuenta de acceso al sistema.</p>
    </div>

    <a
        href="{{ route('usuarios.index') }}"
        class="btn btn-secondary"
    >
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>

@include('users.partials.form')

@endsection
