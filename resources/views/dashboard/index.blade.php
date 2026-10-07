@extends('layouts.app')

@section('title', 'Panel de Control')
@section('page-title', 'Panel de Control')

@section('content')

    <div>

        <div class="eyebrow">
            Sprint 1 • Acceso operativo
        </div>

        <h1>
            Panel de Control
        </h1>

        <p class="muted">
            Bienvenido al Sistema Web de Gestión.
        </p>

    </div>

    @include('dashboard.partials.cards')

    @include('dashboard.partials.quick-access')

    @include('dashboard.partials.sprint-session')

    @include('dashboard.partials.roadmap')

@endsection
