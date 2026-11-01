@extends('layouts.app')
@section('title', isset($pedido) ? 'Editar pedido por unidades' : 'Nuevo pedido por unidades')
@section('page-title', 'Pedidos por unidades físicas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint2.css') }}"><link rel="stylesheet" href="{{ asset('css/pedidos-unidades.css') }}">@endpush
@section('content')
<h1>{{ isset($pedido) ? 'Editar pedido N.º '.$pedido->numero : 'Nuevo pedido por unidades' }}</h1>
<p>Venta por unidades físicas. La entrega suelta es predeterminada. Agrupar no añade productos ni precios y no exige múltiplos.</p>
<p><a class="btn btn-secondary" href="{{ route('pedidos.index') }}">Volver a pedidos</a></p>
@if($errors->any())<div role="alert" class="s2-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<noscript><p role="alert">Activa JavaScript para seleccionar productos y distribuir las unidades de este formulario.</p></noscript>
<form method="POST" action="{{ isset($pedido) ? route('pedidos.update', $pedido) : route('pedidos.store') }}" data-pedido-unidades data-catalogo="{{ route('pedidos.unidades.catalogo') }}">
@csrf @include('components.token-operacion')
<input type="hidden" name="modelo_comercial" value="unidades">
@if(isset($pedido)) @method('PUT')
<input type="hidden" name="_version" value="{{ old('_version', $pedido->version) }}"><input type="hidden" name="_updated_at" value="{{ old('_updated_at', $pedido->getRawOriginal('updated_at')) }}">
@endif
<section class="form-section"><h2>Cliente y fechas</h2><div class="form-grid">
<div class="busqueda-comercial">
<label for="buscar-cliente">Buscar y seleccionar cliente</label><input id="buscar-cliente" class="form-control" type="search" data-buscar-cliente autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="coincidencias-clientes" aria-describedby="estado-clientes" value="{{ $cliente?->nombre }}" placeholder="Nombre, documento o teléfono">
<select name="cliente_id" data-clientes hidden><option value=""></option>@if($cliente)<option value="{{ $cliente->id }}" selected>{{ $cliente->nombre }}</option>@endif</select>
<div id="coincidencias-clientes" class="coincidencias" role="listbox" aria-label="Clientes encontrados" data-coincidencias-clientes hidden></div><button type="button" class="btn btn-secondary" data-mas-clientes hidden>Más coincidencias de clientes</button><p id="estado-clientes" role="status" aria-live="polite" data-estado-clientes>{{ $cliente ? 'Cliente seleccionado: '.$cliente->nombre : 'Selecciona una coincidencia para asociar el cliente.' }}</p>
</div>
<label>Fecha de pedido<input class="form-control" type="date" name="fecha_pedido" required value="{{ old('fecha_pedido', $pedido?->fecha_pedido?->format('Y-m-d') ?? today('America/Lima')->toDateString()) }}"></label>
<label>Fecha de entrega<input class="form-control" type="date" name="fecha_entrega" required value="{{ old('fecha_entrega', $pedido?->fecha_entrega?->format('Y-m-d') ?? today('America/Lima')->toDateString()) }}"></label>
<label>Condición de pago<select class="form-control" name="condicion_pago"><option value="contado" @selected(old('condicion_pago', $pedido?->condicion_pago)==='contado')>Al contado</option><option value="credito" @selected(old('condicion_pago', $pedido?->condicion_pago)==='credito')>Crédito</option></select></label>
<label>Observaciones<textarea class="form-control" name="observaciones" maxlength="2000">{{ old('observaciones', $pedido?->observaciones) }}</textarea></label>
<label>Motivo (obligatorio al editar o dejar unidades en espera)<textarea class="form-control" name="motivo" @required(isset($pedido)) minlength="10" maxlength="1000">{{ old('motivo') }}</textarea></label>
</div></section>
<section class="form-section"><h2>Seleccionar unidades</h2><div class="form-grid">
<div class="busqueda-comercial">
<label for="buscar-producto">Buscar y seleccionar producto</label><input id="buscar-producto" class="form-control" type="search" data-buscar-producto value="{{ $productoSeleccionado?->nombre }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="coincidencias-productos" aria-describedby="estado-productos" placeholder="Nombre, código o SKU">
<select data-productos name="producto_seleccionado" hidden><option value=""></option>@if($productoSeleccionado)<option value="{{ $productoSeleccionado->id }}" selected>{{ $productoSeleccionado->nombre }}</option>@endif</select><div id="coincidencias-productos" class="coincidencias" role="listbox" aria-label="Productos encontrados" data-coincidencias-productos hidden></div><button type="button" class="btn btn-secondary" data-mas-productos hidden>Más coincidencias de productos</button><p id="estado-productos" role="status" aria-live="polite" data-estado-productos>Selecciona un producto para consultar sus variantes y presentaciones.</p>
</div>
<label data-campo-variante hidden>Variante<select class="form-control" data-variantes name="variante_seleccionada"></select></label>
<label>Presentación física<select class="form-control" data-skus name="sku_seleccionado"><option value="">Selecciona primero un producto</option></select></label>
<button type="button" class="btn btn-secondary" data-mas-skus hidden>Más presentaciones y variantes</button><p data-estado-skus role="status" aria-live="polite"></p>
<label>Cantidad de unidades<input class="form-control" data-cantidad-nueva name="cantidad_nueva" type="number" min="1" step="1" value="{{ old('cantidad_nueva', 1) }}" inputmode="numeric"></label>
<button type="button" class="btn btn-primary" data-agregar-linea>Añadir unidades</button>
</div><p>No se comprueba disponibilidad de líquido ni se descuenta inventario al registrar o confirmar.</p>
<p data-mensaje role="status" aria-live="polite"></p>
<div data-lineas></div></section>
<section class="form-section"><h2>Forma de entrega</h2>
<label><input type="checkbox" data-empaquetar> Configurar agrupaciones opcionales</label>
<p>Lo no asignado a un paquete se entrega suelto. Indica la distribución de cada paquete, incluso si no se llena su capacidad.</p>
<div data-grupos-panel hidden><button type="button" class="btn btn-secondary" data-agregar-grupo>Crear paquete</button><div data-grupos></div></div>
<input type="hidden" name="agrupaciones_json" data-agrupaciones-json value="[]">
</section>
<section class="form-section"><h2>Resumen del pedido</h2><p data-resumen aria-live="polite"></p>
<p>En espera conserva obligación de atención. Retirar reduce la cantidad vigente y conserva la versión anterior. No retires unidades ya despachadas.</p>
<button type="submit" class="btn btn-primary">Guardar pedido por unidades</button></section>
</form>
@php
    $datosUnidadesIniciales = ['seleccionSku'=>$seleccionSku, 'lineas'=>$lineas, 'agrupaciones'=>$agrupacionesIniciales, 'bloqueadas' => isset($pedido) ? array_keys(app(\App\Services\DistribuirDespachoUnidades::class)->anteriores($pedido->id)['paquetes']) : []];
@endphp
<script type="application/json" id="pedido-unidades-inicial">@json($datosUnidadesIniciales)</script>
@endsection
@push('scripts')<script src="{{ asset('js/pedidos-autocompletar.js') }}" defer></script><script src="{{ asset('js/pedidos-unidades.js') }}" defer></script>@endpush
