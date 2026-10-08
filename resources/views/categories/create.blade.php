@extends('layouts.app')

@section('title', 'Nueva Categoría')
@section('page-title', 'Nueva Categoría')
@section('breadcrumb', 'Inicio / Productos / Categorías / Nueva')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/products.css') }}?v=1">
@endpush

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">Catálogo comercial</div>
        <h1>Nueva Categoría</h1>
        <p>Registra una categoría para organizar los productos del catálogo.</p>
    </div>

    <a href="{{ route('categorias.index') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
    </a>
</div>

@include('categories.partials.form')
@endsection
