@php
    $user = auth()->user();
    $rol = $user->role?->nombre;
@endphp

<div class="cards-grid" style="margin-top:30px;">

    <div class="card">

        <div class="card-head">

            <div>
                <div class="muted">
                    Usuarios activos
                </div>

                <div class="metric">
                    {{ $usuariosActivos }}
                </div>
            </div>

            <div class="icon-box">
                <span class="material-symbols-outlined">
                    group
                </span>
            </div>

        </div>

        <div class="card-footer">
            {{ $administradores }} Administrador(es)
            • {{ $operacionesComerciales }} Operaciones Comerciales
            • {{ $produccionReparto }} Producción/Reparto
        </div>

    </div>

    <div class="card">

        <div class="card-head">

            <div>
                <div class="muted">
                    Catálogo de productos
                </div>

                <div
                    style="
                        margin-top:16px;
                        color:var(--primary);
                        font-size:20px;
                        font-weight:700;
                    "
                >
                    En desarrollo
                </div>
            </div>

            <div class="icon-box secondary">
                <span class="material-symbols-outlined">
                    inventory_2
                </span>
            </div>

        </div>

        <div class="card-footer">
            Se implementará dentro del Sprint 1.
        </div>

    </div>

    <div class="card">

        <div class="muted">
            Mi rol actual
        </div>

        <div style="margin-top:20px;">
            <span class="role-pill">
                {{ $rol ?? 'Sin rol' }}
            </span>
        </div>

        <div class="card-footer">
            {{ $rol === 'Administrador'
                ? 'Acceso administrativo habilitado.'
                : 'Acceso limitado según permisos asignados.'
            }}
        </div>

    </div>

    <div class="card">

        <div class="muted">
            Estado del sistema
        </div>

        <div
            style="
                margin-top:20px;
                font-size:24px;
                font-weight:700;
            "
        >
            <span style="color:#177642;">
                ●
            </span>

            Operativo
        </div>

        <div class="card-footer">
            Autenticación y base de datos disponibles.
        </div>

    </div>

</div>
