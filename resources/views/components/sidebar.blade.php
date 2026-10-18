@php
    $userSidebar = auth()->user();
    $userSidebar->loadMissing('role.permisos');

    $rolSidebar = $userSidebar->role?->nombre;
    $esAdminSidebar = $rolSidebar === 'Administrador';

    $permisosSidebar = $userSidebar->role?->permisos?->pluck('nombre')->all() ?? [];

    $puedeVerProductosSidebar = in_array(
        'productos.ver',
        $permisosSidebar,
        true
    );

    $puedeVerClientesSidebar = in_array(
        'clientes.ver',
        $permisosSidebar,
        true
    );

    $puedeVerPedidosSidebar = in_array(
        'pedidos.ver',
        $permisosSidebar,
        true
    );

    $puedeVerInventarioSidebar = in_array('inventario.ver', $permisosSidebar, true);

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
                <div class="brand-subtitle">
                    Sistema Web de Gestión
                </div>
            </div>
        </div>

        <nav class="menu">
            <p class="menu-title">Sección principal</p>

            <a
                href="{{ route('dashboard') }}"
                class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
            >
                <span class="material-symbols-outlined">
                    dashboard
                </span>
                Panel de Control
            </a>

            @if($esAdminSidebar)
                <a
                    href="{{ route('usuarios.index') }}"
                    class="menu-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}"
                >
                    <span class="material-symbols-outlined">
                        group
                    </span>
                    Gestión de Usuarios
                </a>

                <a
                    href="{{ route('roles.index') }}"
                    class="menu-link {{ request()->routeIs('roles.*') || request()->routeIs('permisos.*') ? 'active' : '' }}"
                >
                    <span class="material-symbols-outlined">
                        verified_user
                    </span>
                    Roles y Permisos
                </a>
            @endif

            @if($puedeVerProductosSidebar)
                <a
                    href="{{ route('productos.index') }}"
                    class="menu-link {{ request()->routeIs('productos.*') || request()->routeIs('categorias.*') ? 'active' : '' }}"
                >
                    <span class="material-symbols-outlined">
                        inventory_2
                    </span>
                    Catálogo de Productos
                </a>
            @endif

            @if($puedeVerClientesSidebar)
                <a
                    href="{{ route('clientes.index') }}"
                    class="menu-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}"
                >
                    <span class="material-symbols-outlined">
                        groups
                    </span>
                    Gestión de Clientes
                </a>
            @endif

            @if($puedeVerPedidosSidebar)
                <a
                    href="{{ route('pedidos.index') }}"
                    class="menu-link {{ request()->routeIs('pedidos.*') ? 'active' : '' }}"
                >
                    <span class="material-symbols-outlined">
                        shopping_cart
                    </span>
                    Gestión de Pedidos
                </a>
            @endif


            @if($puedeVerPedidosSidebar)
                <a href="{{ route('cumplimiento.index') }}"
                   class="menu-link {{ request()->routeIs('cumplimiento.*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">local_shipping</span>
                    Preparación, despacho y entrega
                </a>
            @endif

            @if($puedeVerInventarioSidebar)
                <p class="menu-title" style="margin-top:25px;">Inventario</p>
                <a href="{{ route('inventario.index') }}"
                   class="menu-link {{ request()->routeIs('inventario.index') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">inventory</span>
                    Disponibilidad
                </a>
                <a href="{{ route('inventario.movimientos.index') }}"
                   class="menu-link {{ request()->routeIs('inventario.movimientos.*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">swap_horiz</span>
                    Movimientos
                </a>
                <a href="{{ route('inventario.stock') }}"
                   class="menu-link {{ request()->routeIs('inventario.stock*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">notification_important</span>
                    Stock y alertas
                </a>
            @endif

            @if(in_array('seguimiento.ver', $permisosSidebar, true))
                <p class="menu-title" style="margin-top:25px;">Seguimiento</p>
                <a href="{{ route('seguimiento.index') }}"
                   class="menu-link {{ request()->routeIs('seguimiento.index') || request()->routeIs('seguimiento.show') || request()->routeIs('seguimiento.trazabilidad') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">travel_explore</span>
                    Consultar estado
                </a>
                <a href="{{ route('seguimiento.historial') }}"
                   class="menu-link {{ request()->routeIs('seguimiento.historial') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">history</span>
                    Historial de pedidos
                </a>
            @endif
        </nav>
    </div>

    <div class="sidebar-user">
        <div class="user-box">
            <div class="avatar">
                {{ $iniciales }}
            </div>

            <div style="flex:1; min-width:0;">
                <div style="font-size:14px; font-weight:700;">
                    {{ $userSidebar->first_name }}
                    {{ $userSidebar->last_name }}
                </div>

                <div
                    class="muted"
                    style="color:var(--primary);"
                >
                    {{ $rolSidebar ?? 'Sin rol' }}
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('logout') }}"
            >
                @csrf

                <button
                    type="submit"
                    class="logout-btn"
                    title="Cerrar sesión"
                >
                    <span class="material-symbols-outlined">
                        logout
                    </span>
                </button>
            </form>
        </div>
    </div>
</aside>
