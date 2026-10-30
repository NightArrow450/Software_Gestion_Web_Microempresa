@php
    $esAdministrador = $usuario->role?->nombre === 'Administrador';
    $grupos = [
        'Gestión comercial' => [
            ['clientes.ver', 'clientes.index', 'groups', 'Clientes', 'Consulta y gestión de clientes.'],
            ['productos.ver', 'productos.index', 'inventory_2', 'Catálogo', 'Productos, aromas y presentaciones.'],
            ['pedidos.ver', 'pedidos.index', 'receipt_long', 'Pedidos', 'Ventas por unidades físicas y notas de pedido.'],
            ['pedidos.crear', 'pedidos.create', 'add_shopping_cart', 'Registrar pedido', 'Unidades físicas y agrupaciones logísticas opcionales.'],
        ],
        'Preparación y distribución' => [
            ['pedidos.ver', 'cumplimiento.lista-preparacion', 'checklist', 'Lista General de Preparación', 'Demanda por producto, presentación y aroma.'],
            ['pedidos.ver', 'cumplimiento.index', 'local_shipping', 'Cumplimiento de pedidos', 'Preparación, despacho, entregas y retornos.'],
            ['seguimiento.ver', 'seguimiento.index', 'travel_explore', 'Seguimiento', 'Consulta de estados y trazabilidad.'],
        ],
        'Inventario y envasado' => [
            ['inventario.ver', 'inventario.index', 'inventory', 'Disponibilidad', 'Existencias de productos terminados e insumos.'],
            ['inventario.ver', 'inventario.movimientos.index', 'swap_horiz', 'Movimientos', 'Entradas, salidas y ajustes trazables.'],
            ['inventario.ver', 'inventario.stock', 'notifications_active', 'Stock y alertas', 'Revisión de niveles y mínimos.'],
            ['envasado.ver', 'envasado.index', 'water_drop', 'Envasados', 'Producción registrada, materiales y correcciones.'],
        ],
        'Información y administración' => [
            ['indicadores.ver', 'indicadores.index', 'monitoring', 'Indicadores y CSV', 'Reportes filtrados y exportaciones según permisos.'],
            ['administrador', 'usuarios.index', 'manage_accounts', 'Usuarios', 'Cuentas y acceso al sistema.'],
            ['administrador', 'roles.index', 'verified_user', 'Roles y permisos', 'Autorizaciones por responsabilidad.'],
            ['auditoria.ver', 'auditoria.index', 'policy', 'Auditoría', 'Acciones sensibles y responsables.'],
        ],
    ];
    $cantidadAccesos = 0;
@endphp
@foreach($grupos as $nombreGrupo => $accesos)
    @php
        $visibles = array_filter($accesos, fn ($acceso) => ($acceso[0] === 'administrador' ? $esAdministrador : ($usuario->role?->tienePermiso($acceso[0]) ?? false)) && ($acceso[0] !== 'auditoria.ver' || $esAdministrador));
        $cantidadAccesos += count($visibles);
    @endphp
    @if($visibles)
    <section class="dashboard-process" aria-labelledby="proceso-{{ $loop->index }}">
        <h2 id="proceso-{{ $loop->index }}">{{ $nombreGrupo }}</h2>
        <div class="dashboard-links">
        @foreach($visibles as [$permiso, $ruta, $icono, $titulo, $descripcion])
            <a href="{{ route($ruta) }}" class="dashboard-link">
                <span class="dashboard-link-icon material-symbols-outlined" aria-hidden="true">{{ $icono }}</span>
                <div><h3>{{ $titulo }}</h3><p>{{ $descripcion }}</p></div>
                <span class="material-symbols-outlined dashboard-arrow" aria-hidden="true">arrow_forward</span>
            </a>
        @endforeach
        </div>
    </section>
    @endif
@endforeach
@if($cantidadAccesos === 0)
<p class="dashboard-note">Tu cuenta no tiene accesos de módulos asignados. Consulta al Administrador.</p>
@endif
