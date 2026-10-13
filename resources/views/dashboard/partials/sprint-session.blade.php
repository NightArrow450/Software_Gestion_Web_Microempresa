@php
    $userSesion = auth()->user();

    $funcionalidades = [
        ['Autenticación de usuarios', true],
        ['Inicio y cierre de sesión', true],
        ['Roles de usuario', true],
        ['Protección de rutas', true],
        ['Gestión de usuarios', true],
        ['Catálogo de productos', true],
        ['Clientes', true],
        ['Registro de pedidos', true],
        ['Procesamiento y estados', true],
    ];
@endphp

<div class="two-columns">

    <section class="card">

        <h2 class="section-title">
            Alcance de Sprints 1 y 2
        </h2>

        <p class="section-description">
            Módulos disponibles en esta versión del software.
        </p>

        <div class="status-list">

            @foreach($funcionalidades as $item)

                <div class="status-row">

                    <span>
                        {{ $item[0] }}
                    </span>

                    @if($item[1])

                        <span class="badge-success">
                            ✓ Implementado
                        </span>

                    @else

                        <span class="badge-pending">
                            Pendiente
                        </span>

                    @endif

                </div>

            @endforeach

        </div>

    </section>

    <section class="card">

        <h2 class="section-title">
            Mi sesión
        </h2>

        <p class="section-description">
            Información del usuario autenticado.
        </p>

        <div class="session-item">

            <div class="session-label">
                Usuario
            </div>

            <div class="session-value">
                {{ $userSesion->first_name }}
                {{ $userSesion->last_name }}
            </div>

        </div>

        <div class="session-item">

            <div class="session-label">
                Correo
            </div>

            <div class="session-value">
                {{ $userSesion->email }}
            </div>

        </div>

        <div class="session-item">

            <div class="session-label">
                Rol
            </div>

            <div class="session-value">
                {{ $userSesion->role?->nombre ?? 'Sin rol' }}
            </div>

        </div>

        <div class="session-item">

            <div class="session-label">
                Estado
            </div>

            <div class="session-value">

                @if($userSesion->status)

                    <span class="badge-success">
                        ● Activo
                    </span>

                @else

                    <span class="badge-pending">
                        ● Inactivo
                    </span>

                @endif

            </div>

        </div>

    </section>

</div>
