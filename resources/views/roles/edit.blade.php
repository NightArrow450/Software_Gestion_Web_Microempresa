@extends('layouts.app')

@section('title', 'Permisos del Rol')
@section('page-title', 'Permisos del Rol')
@section('breadcrumb', 'Inicio / Roles y Permisos / Editar')

@section('content')

<div class="page-heading">
    <div>
        <div class="eyebrow">Configuración de accesos</div>
        <h1>{{ $role->nombre }}</h1>

        <p>
            Configura la información y los permisos correspondientes al rol.
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


@if($role->nombre === 'Administrador')

    <div
        class="alert alert-success"
        style="margin-bottom:22px;"
    >
        <span class="material-symbols-outlined">
            verified_user
        </span>

        <div>
            El Administrador conserva automáticamente todos los permisos.
        </div>
    </div>

@endif


<form
    method="POST"
    action="{{ route('roles.update', $role) }}"
>

    @csrf
    @method('PUT')


    <section class="form-section">

        <h2 class="form-section-title">
            Información del rol
        </h2>

        <div class="form-group">

            <label>
                Nombre
            </label>

            <input
                type="text"
                name="nombre"
                value="{{ old('nombre', $role->nombre) }}"
                {{ $role->es_sistema ? 'disabled' : '' }}
            >

            @if($role->es_sistema)
                <div
                    class="muted"
                    style="margin-top:6px; font-size:12px;"
                >
                    Este es un rol base del sistema y su nombre no puede modificarse.
                </div>
            @endif

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
                        $checked = in_array(
                            (string) $permiso->id,
                            old(
                                'permisos',
                                $permisosAsignados
                            ),
                            true
                        );

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

                        $bloqueado =
                            $role->nombre === 'Administrador'
                            ||
                            $reservado;
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
                            opacity:{{
                                $bloqueado
                                &&
                                $role->nombre !== 'Administrador'
                                    ? '0.65'
                                    : '1'
                            }};
                        "
                    >

                        <input
                            type="checkbox"
                            name="permisos[]"
                            value="{{ $permiso->id }}"

                            {{
                                (
                                    $role->nombre === 'Administrador'
                                    ||
                                    $checked
                                )
                                    ? 'checked'
                                    : ''
                            }}

                            {{ $bloqueado ? 'disabled' : '' }}

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
                                    line-height:1.45;
                                "
                            >
                                {{ $permiso->descripcion }}
                            </span>

                            @if(
                                $reservado
                                &&
                                $role->nombre !== 'Administrador'
                            )

                                <span
                                    style="
                                        display:block;
                                        margin-top:5px;
                                        color:#9a6700;
                                        font-size:11px;
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

            Guardar cambios
        </button>

    </div>

</form>

@endsection