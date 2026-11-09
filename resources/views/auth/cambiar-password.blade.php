@extends('layouts.app')
@section('title', 'Cambiar contraseña')
@section('page-title', 'Cambiar contraseña')
@section('breadcrumb', 'Seguridad / Contraseña')
@section('content')
<section class="form-section">
    <h1 class="form-section-title">Actualiza tu contraseña</h1>
    <p>Antes de continuar, reemplaza la contraseña inicial por una que solo tú conozcas.</p>
    <form method="POST" action="{{ route('password.actualizar') }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="password_actual">Contraseña actual *</label>
                <input class="form-control" type="password" id="password_actual" name="password_actual" autocomplete="current-password" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Nueva contraseña *</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" minlength="10" required>
                <span class="form-help">Usa al menos 10 caracteres, mayúsculas, minúsculas y números.</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirma la nueva contraseña *</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="10" required>
            </div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Guardar contraseña</button></div>
    </form>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary" type="submit">Cerrar sesión</button></form>
</section>
@endsection