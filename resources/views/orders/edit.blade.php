@extends('layouts.app')
@section('title', 'Editar Pedido')
@section('page-title', 'Editar Pedido')
@section('breadcrumb', 'Inicio / Pedidos / Editar')
@section('content')
<div class="page-heading"><div><div class="eyebrow">Registro de pedidos</div><h1>Editar pedido N.º {{ $pedido->numero }}</h1>
<p>La numeración y el vendedor original se conservarán.</p></div>
<a class="btn btn-secondary" href="{{ route('pedidos.show', $pedido) }}"><span class="material-symbols-outlined">arrow_back</span> Volver</a></div>
@include('orders.partials.form')
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint2.css') }}">@endpush
