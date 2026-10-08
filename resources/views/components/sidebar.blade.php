@php
    $userSidebar = auth()->user();
    $userSidebar->loadMissing('role.permisos');

    $rolSidebar = $userSidebar->role?->nombre;
    $esAdminSidebar = $rolSidebar === 'Administrador';
    $permisosSidebar = $userSidebar->role?->permisos?->pluck('nombre')->all() ?? [];
    $puedeVerProductosSidebar = in_array('productos.ver', $permisosSidebar, true);

    $iniciales =
        strtoupper(substr($userSidebar->first_name, 0, 1)) .
        strtoupper(substr($userSidebar->last_name, 0, 1));
@endphp

<aside class="sidebar">
    <div>
        <div class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="Eco Limp">

            <div>
                <div class="brand-name">DHACER ECO LIMP</div>
                <div class="brand-subtitle">Sistema Web de Gestión</div>
            </div>
        </div>

        <nav class="menu">
            <p class="menu-title">Sección principal</p>

            <a
                href="{{ route('dashboard') }}"
                class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
            >
                <span class="material-symbols-outlined">dashboard</span>
                Panel de Control
            </a>

            @if($esAdminSidebar)
                <a
                    href="{{ route('usuarios.index') }}"
                    class="menu-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}"
                >
                    <span class="material-symbols-outlined">group</span>
                    Gestión de Usuarios
                </a>

                <a
                    href="{{ route('roles.index') }}"
                    class="menu-link {{ request()->routeIs('roles.*') || request()->routeIs('permisos.*') ? 'active' : '' }}"
                >
                    <span class="material-symbols-outlined">verified_user</span>
                    Roles y Permisos
                </a>
            @endif

            @if($puedeVerProductosSidebar)
                <a
                    href="{{ route('productos.index') }}"
                    class="menu-link {{ request()->routeIs('productos.*') || request()->routeIs('categorias.*') ? 'active' : '' }}"
                >
                    <span class="material-symbols-outlined">inventory_2</span>
                    Catálogo de Productos
                </a>
            @endif

            <p class="menu-title" style="margin-top:25px;">Próximos módulos</p>

            <div class="menu-link menu-disabled">
                <span class="material-symbols-outlined">shopping_cart</span>
                <span style="flex:1;">Pedidos</span>
                <span class="menu-soon">Próximamente</span>
            </div>

            <div class="menu-link menu-disabled">
                <span class="material-symbols-outlined">warehouse</span>
                <span style="flex:1;">Inventario</span>
                <span class="menu-soon">Próximamente</span>
            </div>

            <div class="menu-link menu-disabled">
                <span class="material-symbols-outlined">local_shipping</span>
                <span style="flex:1;">Seguimiento</span>
                <span class="menu-soon">Próximamente</span>
            </div>
        </nav>
    </div>

    <div class="sidebar-user">
        <div class="user-box">
            <div class="avatar">{{ $iniciales }}</div>

            <div style="flex:1; min-width:0;">
                <div style="font-size:14px; font-weight:700;">
                    {{ $userSidebar->first_name }} {{ $userSidebar->last_name }}
                </div>

                <div class="muted" style="color:var(--primary);">
                    {{ $rolSidebar ?? 'Sin rol' }}
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn" title="Cerrar sesión">
                    <span class="material-symbols-outlined">logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>
