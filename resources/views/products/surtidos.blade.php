@extends('layouts.app')
@section('title', 'Modalidades surtidas')
@section('page-title', 'Modalidades surtidas')
@section('content')
<div class="page-heading"><div><h1>Modalidades surtidas</h1><p>Habilita tamaños, precios y SKU compatibles. La composición de aromas se selecciona en cada pedido.</p></div><a class="btn btn-secondary" href="{{ route('productos.index') }}">Volver al catálogo</a></div>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@foreach(collect([null])->concat($formatos) as $formato)
<details class="form-section" @if(!$formato) open @endif><summary>{{ $formato ? $formato->nombre.($formato->estado ? ' · Activa' : ' · Inactiva') : 'Habilitar nueva modalidad surtida' }}</summary>
<form method="POST" action="{{ $formato ? route('surtidos.update', $formato->id) : route('surtidos.store') }}" data-formato-surtido>
@csrf @include('components.token-operacion') @if($formato) @method('PUT') <input type="hidden" name="version" value="{{ $formato->version }}"> @endif
<div class="form-grid">
<label>Nombre comercial<input class="form-control" name="nombre" required maxlength="160" value="{{ $formato->nombre ?? '' }}"></label>
<label>Producto<select class="form-control" name="producto_id" required><option value="">Selecciona un producto</option>@foreach($productos as $producto)<option value="{{ $producto->id }}" @selected(($formato->producto_id ?? null)===$producto->id)>{{ $producto->nombre }}</option>@endforeach</select></label>
<label>Unidades por paquete<input class="form-control" type="number" min="2" max="1000" step="1" name="unidades_por_empaque" required value="{{ $formato->unidades_por_empaque ?? '' }}"></label>
<label>Precio comercial por paquete S/<input class="form-control" type="number" min="0.01" max="999999.99" step="0.01" name="precio_empaque" required value="{{ $formato->precio_empaque ?? '' }}"></label>
<label><input type="hidden" name="estado" value="0"><input type="checkbox" name="estado" value="1" @checked(!$formato || $formato->estado)> Modalidad activa</label>
</div>
<p>Selecciona SKU del mismo producto, capacidad, unidad y tipo de envase. Esto no fija cantidades ni combinaciones.</p>
@foreach($skus as $sku)
<label data-producto="{{ $sku->producto_id }}" style="display:block;padding:8px"><input type="checkbox" name="skus[]" value="{{ $sku->id }}" @checked($formato && $autorizados->get($formato->id, collect())->contains('presentacion_producto_id', $sku->id))> {{ $sku->producto->nombre }} · {{ $sku->variante?->valor }} · {{ $sku->contenido }} {{ $sku->unidad_medida }} · {{ $sku->tipo_envase }} · {{ $sku->codigo_sku }}</label>
@endforeach
<button class="btn btn-primary" type="submit">Guardar habilitación comercial</button>
</form></details>
@endforeach
@endsection
@push('scripts')<script src="{{ asset('js/surtidos-catalogo.js') }}" defer></script>@endpush
