@extends('layouts.app')
@section('title','Envasado')
@section('page-title','Envasado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/envasado.css') }}">@endpush
@section('content')
<section class="form-section envasado-seccion"><h1>Registrar envasado real</h1><p>Registra únicamente producción nueva. Se descontarán insumos y se ingresará producto terminado automáticamente, sin otra entrada manual.</p>
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ route('envasado.previsualizar') }}">@csrf
<div class="envasado-grid"><label>Presentación<select class="form-control" required name="presentacion_producto_id" id="presentacion-envasado">@foreach($presentaciones as $presentacion)<option value="{{ $presentacion->id }}" @selected((int)old('presentacion_producto_id')===$presentacion->id)>{{ $presentacion->codigo_sku }} · {{ $presentacion->producto->nombre }} · {{ $presentacion->contenido }} {{ $presentacion->unidad_medida }}</option>@endforeach</select></label>
<label>Alternativa configurada<select class="form-control" required name="configuracion_envasado_id" id="configuracion-envasado"><option value="">Selecciona una alternativa compatible con la presentación</option>@foreach($configuraciones as $configuracion)<option value="{{ $configuracion->id }}" data-version="{{ $configuracion->version }}" data-presentacion="{{ $configuracion->presentacion_producto_id }}" @selected((int)old('configuracion_envasado_id')===$configuracion->id)>{{ $configuracion->nombre }} · {{ $configuracion->presentacion->codigo_sku }}</option>@endforeach</select></label>
<input type="hidden" name="version_configuracion" id="version-configuracion" value="">
<label>Unidades realmente envasadas<input class="form-control" required type="number" min="1" max="1000000" step="1" name="cantidad" value="{{ old('cantidad') }}"></label>
<label>Pedido opcional<select class="form-control" name="pedido_id"><option value="">Envasado anticipado (sin pedido)</option>@foreach($pedidos as $pedido)<option value="{{ $pedido->id }}" @selected((int)old('pedido_id')===$pedido->id)>Pedido {{ $pedido->numero }} · {{ str_replace('_',' ',$pedido->estado) }}</option>@endforeach</select></label>
<label>Observaciones<textarea class="form-control" maxlength="1000" name="observaciones">{{ old('observaciones') }}</textarea></label></div>
<p>Si falta una configuración válida, debe registrarla un usuario con permiso para configurar materiales desde el catálogo de productos. El pedido no cambiará de estado y las unidades no quedarán reservadas.</p>
<button class="btn btn-primary">Consultar consumos antes de confirmar</button><a class="btn btn-secondary" href="{{ route('envasado.index') }}">Volver</a></form></section>
@endsection
@push('scripts')<script src="{{ asset('js/envasado.js') }}" defer></script>@endpush
