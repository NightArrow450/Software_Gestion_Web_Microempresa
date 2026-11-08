@if($resumenUsuarios !== null)
<section class="dashboard-admin" aria-labelledby="usuarios-activos">
    <div><p class="dashboard-eyebrow">Administración</p><h2 id="usuarios-activos">Usuarios activos</h2></div>
    <div class="dashboard-user-count"><strong>{{ $resumenUsuarios['total'] }}</strong><span>cuentas activas</span></div>
    <ul class="dashboard-role-counts" aria-label="Desglose de usuarios activos por rol">
        <li><strong>{{ $resumenUsuarios['administradores'] }}</strong> Administrador(es)</li>
        <li><strong>{{ $resumenUsuarios['comerciales'] }}</strong> Operaciones Comerciales</li>
        <li><strong>{{ $resumenUsuarios['produccion'] }}</strong> Producción/Reparto</li>
    </ul>
    <a class="btn btn-secondary" href="{{ route('usuarios.index') }}">Gestionar usuarios</a>
</section>
@endif
