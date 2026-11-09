@extends('layouts.app')
@section('title', 'Panel de Control')
@section('page-title', 'Panel de Control')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v=1">
@endpush
@section('content')
<div class="dashboard-home">
    <section class="dashboard-welcome" aria-labelledby="bienvenida">
        <div>
            <p class="dashboard-eyebrow">DHACER ECO LIMP · Gestión diaria</p>
            <h1 id="bienvenida">Bienvenido, {{ $usuario->first_name }}</h1>
            <p>Del pedido a la entrega, con inventario y trazabilidad en cada proceso.</p>
        </div>
        <div class="dashboard-session">
            <span class="material-symbols-outlined" aria-hidden="true">badge</span>
            <div><span class="muted">Tu acceso</span><strong>{{ $usuario->role?->nombre ?? 'Sin rol asignado' }}</strong></div>
        </div>
    </section>
    @include('dashboard.partials.cards')
    @include('dashboard.partials.quick-access')
    <aside class="dashboard-note">
        <span class="material-symbols-outlined" aria-hidden="true">info</span>
        <p>Los accesos corresponden a tus permisos. Consulta cada módulo para conocer sus registros y estados actuales.</p>
    </aside>
</div>
@endsection
