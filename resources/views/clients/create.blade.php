
@extends('layouts.app')

@section('title', 'Nuevo Cliente')
@section('page-title', 'Nuevo Cliente')
@section('breadcrumb', 'Inicio / Clientes / Nuevo')

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">Operaciones comerciales</div>
        <h1>Nuevo Cliente</h1>
        <p>Registra un cliente para asociarlo posteriormente a sus pedidos.</p>
    </div>

    <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>

@include('clients.partials.form')
@endsection
