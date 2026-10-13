@php
    $usuarioAccesos = auth()->user();
    $usuarioAccesos->loadMissing('role.permisos');
    $permisosAccesos = $usuarioAccesos->role?->permisos?->pluck('nombre')->all() ?? [];
    $puedeVerProductos = in_array('productos.ver', $permisosAccesos, true);
    $puedeCrearProductos = in_array('productos.crear', $permisosAccesos, true);
    $puedeVerClientes = in_array('clientes.ver', $permisosAccesos, true);
    $puedeVerPedidos = in_array('pedidos.ver', $permisosAccesos, true);
    $puedeCrearPedidos = in_array('pedidos.crear', $permisosAccesos, true);
@endphp

<section class="section">
    <div style="display:flex; align-items:center; gap:12px;">
        <div class="icon-box">
            <span class="material-symbols-outlined">bolt</span>
        </div>

        <div>
            <h2 class="section-title">Accesos rápidos</h2>
            <p class="section-description">Funciones disponibles en el sistema.</p>
        </div>
    </div>

    <div class="quick-grid">
        @if($usuarioAccesos->role?->nombre === 'Administrador')
            <a href="{{ route('usuarios.index') }}" class="quick-card">
                <span class="material-symbols-outlined">group</span>
                <h3>Gestionar Usuarios</h3>
                <p>Registrar, consultar y administrar usuarios.</p>
            </a>

            <a href="{{ route('roles.index') }}" class="quick-card">
                <span class="material-symbols-outlined">verified_user</span>
                <h3>Roles y Permisos</h3>
                <p>Administrar niveles de acceso del sistema.</p>
            </a>
        @endif

        @if($puedeVerProductos)
            <a href="{{ route('productos.index') }}" class="quick-card">
                <span class="material-symbols-outlined">inventory_2</span>
                <h3>Catálogo de Productos</h3>
                <p>Consultar productos, variantes y presentaciones.</p>
            </a>
        @endif

        @if($puedeCrearProductos)
            <a href="{{ route('productos.create') }}" class="quick-card">
                <span class="material-symbols-outlined">add_box</span>
                <h3>Nuevo Producto</h3>
                <p>Registrar un nuevo producto en el catálogo.</p>
            </a>
        @endif

        @if($puedeVerClientes)
            <a href="{{ route('clientes.index') }}" class="quick-card">
                <span class="material-symbols-outlined">contacts</span>
                <h3>Clientes</h3>
                <p>Administrar clientes y su información de mercado.</p>
            </a>
        @endif
        @if($puedeVerPedidos)
            <a href="{{ route('pedidos.index') }}" class="quick-card">
                <span class="material-symbols-outlined">receipt_long</span>
                <h3>Pedidos</h3>
                <p>Revisar estados e imprimir dos notas en A4 horizontal.</p>
            </a>
        @endif
        @if($puedeCrearPedidos)
            <a href="{{ route('pedidos.create') }}" class="quick-card">
                <span class="material-symbols-outlined">add_shopping_cart</span>
                <h3>Nuevo Pedido</h3>
                <p>Registrar un pedido desde el catálogo de presentaciones.</p>
            </a>
        @endif
    </div>
</section>
