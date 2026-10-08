<section class="section">

    <div style="display:flex; align-items:center; gap:12px;">

        <div class="icon-box">
            <span class="material-symbols-outlined">
                bolt
            </span>
        </div>

        <div>
            <h2 class="section-title">
                Accesos rápidos
            </h2>

            <p class="section-description">
                Funciones disponibles durante el Sprint 1.
            </p>
        </div>

    </div>

    <div class="quick-grid">

        @if(auth()->user()->role?->nombre === 'Administrador')

            <a
                href="{{ route('usuarios.index') }}"
                class="quick-card"
            >
                <span class="material-symbols-outlined">
                    group
                </span>

                <h3>Gestionar Usuarios</h3>

                <p>
                    Registrar, consultar y administrar usuarios.
                </p>
            </a>

            <a
                href="{{ route('usuarios.create') }}"
                class="quick-card"
            >
                <span class="material-symbols-outlined">
                    person_add
                </span>

                <h3>Nuevo Usuario</h3>

                <p>
                    Registrar una nueva cuenta de acceso.
                </p>
            </a>

            <a
                href="{{ route('roles.index') }}"
                class="quick-card"
            >

                <span class="material-symbols-outlined">
                    verified_user
                </span>

                <h3>Roles y Permisos</h3>

                <p>
                    Consultar roles y administrar niveles de acceso.
                </p>

            </a>

        @endif

        <div class="quick-card disabled">

            <span class="material-symbols-outlined">
                inventory_2
            </span>

            <h3>Catálogo de Productos</h3>

            <p>
                Pendiente de implementar.
            </p>

        </div>

    </div>

</section>
