@extends('layouts.app')

@section('title', 'Editar Permiso')
@section('page-title', 'Editar Permiso')
@section('breadcrumb', 'Inicio / Roles y Permisos / Editar Permiso')

@section('content')

<div class="page-heading">

    <div>
        <div class="eyebrow">Seguridad y acceso</div>

        <h1>Editar permiso</h1>

        <p>
            Modifica la información correspondiente al permiso.
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
    action="{{ route('permisos.update', $permiso) }}"
>

    @csrf
    @method('PUT')


    <section class="form-section">

        <h2 class="form-section-title">
            Información del permiso
        </h2>


        <div class="form-group">

            <label>
                Clave del permiso
            </label>

            <input
                type="text"
                name="nombre"
                value="{{ old('nombre', $permiso->nombre) }}"
                {{ $permiso->es_sistema ? 'disabled' : '' }}
            >

            @if($permiso->es_sistema)

                <div
                    class="muted"
                    style="margin-top:6px; font-size:12px;"
                >
                    La clave de un permiso base no puede modificarse porque puede estar utilizada por el código del sistema.
                </div>

            @else

                <div
                    class="muted"
                    style="margin-top:6px; font-size:12px;"
                >
                    Si este permiso ya está utilizado en una ruta o funcionalidad, cambiar la clave también requerirá actualizar el código correspondiente.
                </div>

            @endif

        </div>


        <div class="form-group">

            <label>
                Descripción
            </label>

            <textarea
                name="descripcion"
                rows="4"
            >{{ old('descripcion', $permiso->descripcion) }}</textarea>

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

            Guardar cambios
        </button>

    </div>

</form>

@endsection