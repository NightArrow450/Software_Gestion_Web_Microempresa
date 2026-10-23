@extends('layouts.app')
@section('title', 'Materiales de envasado')
@section('page-title', 'Materiales de envasado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/envasado.css') }}">@endpush
@section('content')
<div class="page-heading"><div><h1>Materiales de envasado</h1><p>{{ $presentacion->codigo_sku }} · {{ $presentacion->producto->nombre }}</p></div><a class="btn btn-secondary" href="{{ route('productos.show',$presentacion->producto_id) }}">Volver al producto</a></div>
<p>Configura alternativas compatibles de envase y materiales. No se sustituyen contratapas automáticamente. Las tapas normales solo deben agregarse si la empresa confirmó su control separado.</p>
<p>Consumo por empaque: estructura preparada para materiales como mangas en metros. No se descuenta durante el envasado; requiere un registro futuro de empaques realmente armados y consumos validados.</p>
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@php $alternativas = $configuraciones->concat([null]); @endphp
@foreach($alternativas as $configuracion)
<section class="form-section envasado-seccion"><h2>{{ $configuracion ? $configuracion->nombre : 'Nueva alternativa de envase' }}</h2>
<form method="POST" action="{{ $configuracion ? route('envasado.materiales.update',[$presentacion,$configuracion]) : route('envasado.materiales.store',$presentacion) }}" data-materiales>
@csrf @include('components.token-operacion') @if($configuracion) @method('PUT') @endif
<input type="hidden" name="version" value="{{ $configuracion?->version ?? 0 }}">
<label>Nombre de la alternativa<input class="form-control" required name="nombre" maxlength="120" value="{{ $configuracion?->nombre }}"></label>
<label>Estado<select class="form-control" name="estado"><option value="1" @selected($configuracion?->estado ?? true)>Activa</option><option value="0" @selected($configuracion && !$configuracion->estado)>Inactiva</option></select></label>
<div data-filas-materiales>
@foreach(($configuracion?->materiales->where('estado',true)->values() ?? collect([null])) as $indice=>$material)
@include('envasado.partials.material', ['indice'=>$indice,'material'=>$material])
@endforeach
</div>
<template>@include('envasado.partials.material',['indice'=>'__INDICE__','material'=>null])</template>
<button class="btn btn-secondary" type="button" data-agregar-material>Agregar material</button>
<button class="btn btn-primary" type="submit">Guardar configuración</button>
</form></section>
@endforeach
@if($configuraciones->isEmpty())<p>Esta presentación todavía no tiene configuración de materiales.</p>@endif
@endsection
@push('scripts')<script src="{{ asset('js/envasado.js') }}" defer></script>@endpush
