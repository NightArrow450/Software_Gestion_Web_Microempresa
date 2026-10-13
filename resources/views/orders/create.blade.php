@extends('layouts.app')
@section('title', 'Nuevo Pedido')
@section('page-title', 'Nuevo Pedido')
@section('breadcrumb', 'Inicio / Pedidos / Nuevo')
@section('content')
<div class="page-heading"><div><div class="eyebrow">Registro de pedidos</div><h1>Nuevo Pedido</h1>
<p>Selecciona cliente, presentaciones y fecha prevista de entrega. El correlativo se asigna al guardar.</p></div>
<a class="btn btn-secondary" href="{{ route('pedidos.index') }}"><span class="material-symbols-outlined">arrow_back</span> Volver</a></div>
@include('orders.partials.form')
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint2.css') }}">@endpush
