
@extends('layouts.app')

@section('title', 'Editar Cliente')
@section('page-title', 'Editar Cliente')
@section('breadcrumb', 'Inicio / Clientes / Editar')

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">Operaciones comerciales</div>
        <h1>Editar Cliente</h1>
        <p>{{ $cliente->nombre }}</p>
    </div>

    <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>

@include('clients.partials.form')
@endsection
