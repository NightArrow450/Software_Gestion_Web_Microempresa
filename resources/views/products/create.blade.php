@extends('layouts.app')

@section('title', 'Nuevo Producto')
@section('page-title', 'Nuevo Producto')
@section('breadcrumb', 'Inicio / Productos / Nuevo')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/products.css') }}?v=1">
@endpush

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">Catálogo comercial</div>
        <h1>Nuevo Producto</h1>
        <p>Registra la información, variantes y presentaciones comerciales del producto.</p>
    </div>

    <a href="{{ route('productos.index') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>

@include('products.partials.form')
@endsection
