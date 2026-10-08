@extends('layouts.app')

@section('title', 'Nuevo Rol')
@section('page-title', 'Nuevo Rol')
@section('breadcrumb', 'Inicio / Roles y Permisos / Nuevo Rol')

@section('content')

<div class="page-heading">
    <div>
        <div class="eyebrow">Seguridad y acceso</div>
        <h1>Crear nuevo rol</h1>
        <p>
            Define el nombre del rol y selecciona los permisos que tendrá dentro del sistema.
        </p>
    </div>

    <a href="{{ route('roles.index') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>


@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:22px;">
        <span class="material-symbols-outlined">error</span>
        <div>
            <strong>Revisa la información ingresada.</strong>

            <ul style="margin:7px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif


<form method="POST" action="{{ route('roles.store') }}">

    @csrf

    <section class="form-section">

        <h2 class="form-section-title">
            Información del rol
        </h2>

        <div class="form-group">
            <label for="nombre">
                Nombre del rol
            </label>

            <input
                type="text"
                id="nombre"
                name="nombre"
                value="{{ old('nombre') }}"
                placeholder="Ejemplo: Supervisor"
                required
            >
        </div>

    </section>


    @foreach($permisosAgrupados as $nombreGrupo => $permisosGrupo)

        <section class="form-section">

            <h2 class="form-section-title">
                {{ $nombreGrupo }}
            </h2>

            <div
                style="
                    display:grid;
                    grid-template-columns:repeat(2, minmax(0, 1fr));
                    gap:12px;
                "
            >

                @foreach($permisosGrupo as $permiso)

                    @php
                        $reservado =
                            str_starts_with(
                                $permiso->nombre,
                                'usuarios.'
                            )
                            ||
                            str_starts_with(
                                $permiso->nombre,
                                'roles.'
                            );

                        $checked = in_array(
                            (string) $permiso->id,
                            old('permisos', []),
                            true
                        );
                    @endphp

                    <label
                        style="
                            display:flex;
                            align-items:flex-start;
                            gap:12px;
                            padding:15px;
                            border:1px solid #edf0ee;
                            border-radius:10px;
                            background:#ffffff;
                            opacity:{{ $reservado ? '0.65' : '1' }};
                        "
                    >

                        <input
                            type="checkbox"
                            name="permisos[]"
                            value="{{ $permiso->id }}"
                            {{ $checked ? 'checked' : '' }}
                            {{ $reservado ? 'disabled' : '' }}
                            style="
                                width:17px;
                                height:17px;
                                margin-top:2px;
                                accent-color:var(--primary);
                            "
                        >

                        <span>
                            <strong style="display:block;">
                                {{ $permiso->nombre }}
                            </strong>

                            <span
                                class="muted"
                                style="
                                    display:block;
                                    margin-top:4px;
                                    font-size:12px;
                                "
                            >
                                {{ $permiso->descripcion }}
                            </span>

                            @if($reservado)
                                <span
                                    style="
                                        display:block;
                                        color:#9a6700;
                                        font-size:11px;
                                        margin-top:5px;
                                        font-weight:700;
                                    "
                                >
                                    Exclusivo del Administrador
                                </span>
                            @endif
                        </span>

                    </label>

                @endforeach

            </div>

        </section>

    @endforeach


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

            Crear rol
        </button>

    </div>

</form>

@endsection