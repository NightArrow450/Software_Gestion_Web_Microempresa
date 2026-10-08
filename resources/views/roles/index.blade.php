@extends('layouts.app')

@section('title', 'Roles y Permisos')
@section('page-title', 'Roles y Permisos')
@section('breadcrumb', 'Inicio / Roles y Permisos')

@section('content')

<div class="page-heading">

    <div>
        <div class="eyebrow">Seguridad y acceso</div>

        <h1>Roles y Permisos</h1>

        <p>
            Administra los roles y las acciones disponibles dentro del sistema.
        </p>
    </div>

    <div style="display:flex; gap:10px;">

        <a
            href="{{ route('roles.create') }}"
            class="btn btn-primary"
        >
            <span class="material-symbols-outlined">
                group_add
            </span>

            Nuevo rol
        </a>


        <a
            href="{{ route('permisos.create') }}"
            class="btn btn-secondary"
        >
            <span class="material-symbols-outlined">
                add_moderator
            </span>

            Nuevo permiso
        </a>

    </div>

</div>


@if(session('success'))

    <div
        class="alert alert-success"
        style="margin-bottom:22px;"
    >
        <span class="material-symbols-outlined">
            check_circle
        </span>

        <div>
            {{ session('success') }}
        </div>
    </div>

@endif


@if(session('error'))

    <div
        class="alert alert-danger"
        style="margin-bottom:22px;"
    >
        <span class="material-symbols-outlined">
            error
        </span>

        <div>
            {{ session('error') }}
        </div>
    </div>

@endif


{{-- ROLES --}}

<section class="table-card">

    <div class="table-header">

        <strong>
            Roles configurados
        </strong>

        <div
            class="muted"
            style="margin-top:4px;"
        >
            {{ $roles->count() }} rol(es) disponibles
        </div>

    </div>


    <div class="table-container">

        <table class="app-table">

            <thead>
                <tr>
                    <th>Rol</th>
                    <th>Usuarios</th>
                    <th>Permisos</th>
                    <th>Tipo</th>
                    <th style="text-align:right;">
                        Acciones
                    </th>
                </tr>
            </thead>


            <tbody>

                @foreach($roles as $role)

                    <tr>

                        <td>

                            <strong>
                                {{ $role->nombre }}
                            </strong>

                            @if($role->nombre === 'Administrador')

                                <div
                                    style="
                                        margin-top:3px;
                                        color:var(--primary);
                                        font-size:11px;
                                        font-weight:700;
                                    "
                                >
                                    Rol principal del sistema
                                </div>

                            @endif

                        </td>


                        <td>
                            {{ $role->users_count }}
                        </td>


                        <td>
                            <span class="role-badge">
                                {{ $role->permisos_count }} permisos
                            </span>
                        </td>


                        <td>

                            @if($role->es_sistema)

                                <span class="status-badge status-active">
                                    ● Sistema
                                </span>

                            @else

                                <span class="role-badge">
                                    Personalizado
                                </span>

                            @endif

                        </td>


                        <td>

                            <div class="table-actions">

                                <a
                                    href="{{ route('roles.edit', $role) }}"
                                    class="icon-button"
                                    title="Editar rol y permisos"
                                >
                                    <span class="material-symbols-outlined">
                                        tune
                                    </span>
                                </a>


                                @if(!$role->es_sistema)

                                    <form
                                        method="POST"
                                        action="{{ route('roles.destroy', $role) }}"
                                        onsubmit="
                                            return confirm(
                                                '¿Seguro que deseas eliminar este rol?'
                                            );
                                        "
                                    >

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="icon-button"
                                            title="Eliminar rol"
                                        >
                                            <span class="material-symbols-outlined">
                                                delete
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

</section>



{{-- PERMISOS --}}

<section
    class="table-card"
    style="margin-top:22px;"
>

    <div class="table-header">

        <strong>
            Permisos disponibles
        </strong>

        <div
            class="muted"
            style="margin-top:4px;"
        >
            {{ $permisos->count() }} permiso(s) registrados
        </div>

    </div>


    <div class="table-container">

        <table class="app-table">

            <thead>
                <tr>
                    <th>Permiso</th>
                    <th>Descripción</th>
                    <th>Roles</th>
                    <th>Tipo</th>
                    <th style="text-align:right;">
                        Acciones
                    </th>
                </tr>
            </thead>


            <tbody>

                @foreach($permisos as $permiso)

                    <tr>

                        <td>
                            <strong>
                                {{ $permiso->nombre }}
                            </strong>
                        </td>


                        <td>
                            <span class="muted">
                                {{ $permiso->descripcion ?? 'Sin descripción' }}
                            </span>
                        </td>


                        <td>
                            <span class="role-badge">
                                {{ $permiso->roles_count }} roles
                            </span>
                        </td>


                        <td>

                            @if($permiso->es_sistema)

                                <span class="status-badge status-active">
                                    ● Sistema
                                </span>

                            @else

                                <span class="role-badge">
                                    Personalizado
                                </span>

                            @endif

                        </td>


                        <td>

                            <div class="table-actions">

                                <a
                                    href="{{ route('permisos.edit', $permiso) }}"
                                    class="icon-button"
                                    title="Editar permiso"
                                >
                                    <span class="material-symbols-outlined">
                                        edit
                                    </span>
                                </a>


                                @if(!$permiso->es_sistema)

                                    <form
                                        method="POST"
                                        action="{{ route('permisos.destroy', $permiso) }}"
                                        onsubmit="
                                            return confirm(
                                                '¿Seguro que deseas eliminar este permiso?'
                                            );
                                        "
                                    >

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="icon-button"
                                            title="Eliminar permiso"
                                        >
                                            <span class="material-symbols-outlined">
                                                delete
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

</section>


<section
    class="card"
    style="margin-top:22px;"
>

    <div
        style="
            display:flex;
            gap:14px;
            align-items:flex-start;
        "
    >

        <div class="icon-box">
            <span class="material-symbols-outlined">
                info
            </span>
        </div>

        <div>

            <strong>
                Control de seguridad
            </strong>

            <p
                class="muted"
                style="
                    margin:7px 0 0;
                    line-height:1.6;
                "
            >
                Los roles y permisos base se encuentran protegidos.
                Puedes crear roles adicionales y permisos personalizados
                sin modificar la estructura principal del sistema.
            </p>

        </div>

    </div>

</section>

@endsection