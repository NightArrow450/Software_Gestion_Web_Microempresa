@extends('layouts.app')
@section('title', 'Nuevo movimiento de inventario')
@section('page-title', 'Nuevo movimiento de inventario')
@section('breadcrumb', 'Inicio / Inventario / Movimientos / Nuevo')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint3.css') }}">@endpush
@section('content')
<div class="page-heading"><div><div class="eyebrow">Inventario · HU-05</div><h1>Registrar movimiento</h1>
    <p>Selecciona un producto terminado O un insumo. Se registrarán las cantidades anterior y nueva, el motivo y el responsable.</p></div>
    <a class="btn btn-secondary" href="{{ route('inventario.movimientos.index') }}">Volver</a></div>
<section class="form-section">
<form method="POST" action="{{ route('inventario.movimientos.store') }}">
    @csrf
    <div class="s3-formgrid">
        <div class="form-group"><label class="form-label" for="presentacion_producto_id">Producto terminado (opcional)</label>
            <select class="form-control" name="presentacion_producto_id" id="presentacion_producto_id">
                <option value="">No seleccionar producto</option>
                @foreach($presentaciones as $p)
                    <option value="{{ $p->id }}" @selected((int)old('presentacion_producto_id')===$p->id)>
                        {{ $p->codigo_sku }} — {{ $p->producto?->nombre }} — {{ $p->contenido }} {{ $p->unidad_medida }}
                        (Stock: {{ (int)($existencias[$p->id] ?? 0) }} u.)
                    </option>
                @endforeach
            </select>
            @error('presentacion_producto_id')<div class="s3-status s3-danger">{{ $message }}</div>@enderror
        </div>
        <div class="form-group"><label class="form-label" for="insumo_id">Insumo / envase / material (opcional)</label>
            <select class="form-control" name="insumo_id" id="insumo_id">
                <option value="">No seleccionar insumo</option>
                @foreach($insumos as $i)
                    <option value="{{ $i->id }}" @selected((int)old('insumo_id')===$i->id)>
                        {{ $i->codigo }} — {{ $i->nombre }} ({{ $i->cantidad_actual }} {{ $i->unidad_medida }})
                    </option>
                @endforeach
            </select>
            <p class="s3-muted">Selecciona SOLO UNO de los dos campos anteriores.</p>
            @error('insumo_id')<div class="s3-status s3-danger">{{ $message }}</div>@enderror
        </div>
        <div class="form-group"><label class="form-label" for="tipo">Tipo de movimiento *</label>
            <select required class="form-control" name="tipo" id="tipo">
                <option value="">Seleccione</option>
                @foreach(['entrada'=>'Entrada (+)','salida'=>'Salida (-)','ajuste'=>'Ajuste (stock físico final)'] as $tipo=>$nombre)
                    @if(isset($tiposDisponibles[$tipo]))
                        <option value="{{ $tipo }}" @selected(old('tipo')===$tipo)>{{ $nombre }}</option>
                    @endif
                @endforeach
            </select>
            @error('tipo')<div class="s3-status s3-danger">{{ $message }}</div>@enderror
        </div>
        <div class="form-group"><label class="form-label" for="cantidad">Cantidad *</label>
            <input class="form-control" type="number" name="cantidad" id="cantidad" min="0" step="0.001"
                max="1000000000" required value="{{ old('cantidad') }}">
            <p class="s3-muted">Producto terminado: unidades enteras. Insumos: piezas, litros o kg, hasta 3 decimales. En ajustes coloca el stock FINAL.</p>
            @error('cantidad')<div class="s3-status s3-danger">{{ $message }}</div>@enderror
        </div>
        <div class="form-group"><label class="form-label" for="motivo">Motivo / referencia *</label>
            <textarea required minlength="5" maxlength="1000" class="form-control" name="motivo" id="motivo" rows="3"
                placeholder="Compra de botellas, consumo de tapas, ingreso por producción, merma, conteo físico...">{{ old('motivo') }}</textarea>
            @error('motivo')<div class="s3-status s3-danger">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="s3-actions"><button class="btn btn-primary" type="submit">Guardar movimiento</button>
        <a class="btn btn-secondary" href="{{ route('inventario.movimientos.index') }}">Cancelar</a></div>
</form>
</section>
@endsection
