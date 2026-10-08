@extends('layouts.app')

@section('title', 'Editar Categoría')
@section('page-title', 'Editar Categoría')
@section('breadcrumb', 'Inicio / Productos / Categorías / Editar')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/products.css') }}?v=1">
@endpush

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">Catálogo comercial</div>
        <h1>Editar Categoría</h1>
        <p>Actualiza la información de {{ $categoria->nombre }}.</p>
    </div>

    <a href="{{ route('categorias.show', $categoria) }}" class="btn btn-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>

@include('categories.partials.form')
@endsection
