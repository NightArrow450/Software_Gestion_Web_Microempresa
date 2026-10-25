@if($resumenUsuarios !== null)
<section class="dashboard-admin" aria-labelledby="usuarios-activos">
    <div><p class="dashboard-eyebrow">Administración</p><h2 id="usuarios-activos">Usuarios activos</h2></div>
    <div class="dashboard-user-count"><strong>{{ $resumenUsuarios['total'] }}</strong><span>cuentas activas</span></div>
    <p>{{ $resumenUsuarios['administradores'] }} Administrador(es) · {{ $resumenUsuarios['comerciales'] }} Operaciones Comerciales · {{ $resumenUsuarios['produccion'] }} Producción/Reparto</p>
    <a class="btn btn-secondary" href="{{ route('usuarios.index') }}">Gestionar usuarios</a>
</section>
@endif
