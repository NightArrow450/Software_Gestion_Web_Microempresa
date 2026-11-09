@extends('layouts.app')

@section('title', 'Nuevo Permiso')
@section('page-title', 'Nuevo Permiso')
@section('breadcrumb', 'Inicio / Roles y Permisos / Nuevo Permiso')

@section('content')

<div class="page-heading">

    <div>
        <div class="eyebrow">Seguridad y acceso</div>

        <h1>Crear nuevo permiso</h1>

        <p>
            Registra una nueva acción que posteriormente podrá asignarse a los roles.
        </p>
    </div>

    <a
        href="{{ route('roles.index') }}"
        class="btn btn-secondary"
    >
        <span class="material-symbols-outlined">
            arrow_back
        </span>

        Volver
    </a>

</div>


<form
    method="POST"
    action="{{ route('permisos.store') }}"
>

    @csrf

    <section class="form-section">

        <h2 class="form-section-title">
            Información del permiso
        </h2>


        <div class="form-group">

            <label for="nombre">
                Clave del permiso
            </label>

            <input
                type="text"
                id="nombre"
                name="nombre"
                value="{{ old('nombre') }}"
                placeholder="Ejemplo: reportes.ver"
                required
            >

            <div
                class="muted"
                style="margin-top:6px; font-size:12px;"
            >
                Usa el formato módulo.acción, por ejemplo:
                inventario.ver o reportes.exportar.
            </div>

        </div>


        <div class="form-group">

            <label for="descripcion">
                Descripción
            </label>

            <textarea
                id="descripcion"
                name="descripcion"
                rows="4"
                placeholder="Describe qué permite realizar esta acción."
            >{{ old('descripcion') }}</textarea>

        </div>

    </section>


    <div class="form-actions">

        <a
            href="{{ route('roles.index') }}"
            class="btn btn-secondary"
        >
            Cancelar
        </a>

        <button
            type="submit"
            class="btn btn-primary"
        >
            <span class="material-symbols-outlined">
                save
            </span>

            Crear permiso
        </button>

    </div>

</form>

@endsection