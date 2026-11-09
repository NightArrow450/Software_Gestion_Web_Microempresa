@extends('layouts.app')

@section('title', 'Editar Producto')
@section('page-title', 'Editar Producto')
@section('breadcrumb', 'Inicio / Productos / Editar')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/products.css') }}?v=1">
@endpush

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">Catálogo comercial</div>
        <h1>Editar Producto</h1>
        <p>Actualiza los datos comerciales de {{ $producto->nombre }}.</p>
    </div>

    <a href="{{ route('productos.show', $producto) }}" class="btn btn-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>

@include('products.partials.form')
@endsection
