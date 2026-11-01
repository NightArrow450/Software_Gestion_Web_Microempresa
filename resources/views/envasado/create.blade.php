@extends('layouts.app')
@section('title','Envasado')
@section('page-title','Envasado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/envasado.css') }}">@endpush
@section('content')
@php($controlMateriales = (bool) config('envasado.control_materiales_exacto'))
<section class="form-section envasado-seccion"><h1>Registrar envasado real</h1><p>Registra únicamente las unidades físicamente terminadas. El stock aumentará al confirmar, sin otra entrada manual. @if($controlMateriales) El control exacto de materiales está habilitado. @else No se descuentan envases, etiquetas, contratapas, químicos ni líquidos. @endif</p>
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ route('envasado.previsualizar') }}">@csrf
<input type="hidden" name="control_materiales_exacto" value="{{ $controlMateriales ? 1 : 0 }}">
<div class="envasado-grid"><label>Presentación<select class="form-control" required name="presentacion_producto_id" id="presentacion-envasado">@foreach($presentaciones as $presentacion)<option value="{{ $presentacion->id }}" @selected((int)old('presentacion_producto_id')===$presentacion->id)>{{ $presentacion->codigo_sku }} · {{ $presentacion->producto->nombre }} {{ $presentacion->variante ? '· '.$presentacion->variante->valor : '' }} · {{ $presentacion->contenido }} {{ $presentacion->unidad_medida }}</option>@endforeach</select></label>
@if($controlMateriales)<label>Alternativa configurada<select class="form-control" required name="configuracion_envasado_id" id="configuracion-envasado"><option value="">Selecciona una alternativa compatible con la presentación</option>@foreach($configuraciones as $configuracion)<option value="{{ $configuracion->id }}" data-version="{{ $configuracion->version }}" data-presentacion="{{ $configuracion->presentacion_producto_id }}" @selected((int)old('configuracion_envasado_id')===$configuracion->id)>{{ $configuracion->nombre }} · {{ $configuracion->presentacion->codigo_sku }}</option>@endforeach</select></label>@endif
<input type="hidden" name="version_configuracion" id="version-configuracion" value="">
<label>Unidades realmente envasadas<input class="form-control" required type="number" min="1" max="1000000" step="1" name="cantidad" value="{{ old('cantidad') }}"></label>
<label>Pedido opcional<select class="form-control" name="pedido_id"><option value="">Envasado anticipado (sin pedido)</option>@foreach($pedidos as $pedido)<option value="{{ $pedido->id }}" @selected((int)old('pedido_id')===$pedido->id)>Pedido {{ $pedido->numero }} · {{ str_replace('_',' ',$pedido->estado) }}</option>@endforeach</select></label>
<label>Observaciones<textarea class="form-control" maxlength="1000" name="observaciones">{{ old('observaciones') }}</textarea></label></div>
<p>@if($controlMateriales)Se requiere una configuración válida y existencias exactas verificadas para este modo opcional. @endif El pedido no cambiará de estado y las unidades no quedarán reservadas.</p>
<button class="btn btn-primary">{{ $controlMateriales ? 'Consultar consumos antes de confirmar' : 'Revisar unidades antes de confirmar' }}</button><a class="btn btn-secondary" href="{{ route('envasado.index') }}">Volver</a></form></section>
@endsection
@push('scripts')<script src="{{ asset('js/envasado.js') }}" defer></script>@endpush
