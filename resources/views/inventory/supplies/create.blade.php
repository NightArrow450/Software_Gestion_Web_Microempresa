@extends('layouts.app')
@section('title', 'Registrar insumo')
@section('page-title', 'Registrar insumo')
@section('breadcrumb', 'Inicio / Inventario / Nuevo insumo')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint3.css') }}">@endpush
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">Inventario · HU-04</div><h1>Registrar insumo o material</h1>
        <p>Ejemplos: galón vacío de 3,5 L, botella de 1 L, tapa, etiqueta, terokal o insumo químico.</p></div>
    <a class="btn btn-secondary" href="{{ route('inventario.index') }}">Volver</a>
</div>
<section class="form-section">
    <form method="POST" action="{{ route('inventario.insumos.store') }}">
        @csrf
        <div class="s3-formgrid">
            <div class="form-group"><label class="form-label" for="codigo">Código único *</label>
                <input class="form-control" id="codigo" name="codigo" maxlength="50" required value="{{ old('codigo') }}" placeholder="ENV-GAL-35L">
                @error('codigo')<p class="s3-status s3-danger">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label class="form-label" for="nombre">Nombre del insumo *</label>
                <input class="form-control" id="nombre" name="nombre" maxlength="160" required value="{{ old('nombre') }}" placeholder="Galón vacío de 3,5 L">
                @error('nombre')<p class="s3-status s3-danger">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label class="form-label" for="categoria">Categoría *</label>
                <select class="form-control" name="categoria" id="categoria" required>
                    <option value="">Selecciona una categoría</option>
                    @foreach(['Envases','Tapas','Etiquetas','Químicos','Otros'] as $categoria)
                        <option value="{{ $categoria }}" @selected(old('categoria')===$categoria)>{{ $categoria }}</option>
                    @endforeach
                </select>
                @error('categoria')<p class="s3-status s3-danger">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label class="form-label" for="unidad_medida">Unidad de control *</label>
                <select class="form-control" name="unidad_medida" id="unidad_medida" required>
                    @foreach(['unidad'=>'Unidades (botellas, galones, tapas, etiquetas)','litro'=>'Litros','kilogramo'=>'Kilogramos','metro'=>'Metros','paquete'=>'Paquetes'] as $valor=>$etiqueta)
                        <option value="{{ $valor }}" @selected(old('unidad_medida','unidad')===$valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('unidad_medida')<p class="s3-status s3-danger">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label class="form-label" for="cantidad_actual">Cantidad física inicial *</label>
                <input type="number" class="form-control" name="cantidad_actual" id="cantidad_actual" min="0" step="0.001" required value="{{ old('cantidad_actual', '0') }}">
                <p class="s3-muted">Ejemplo: 200 botellas, 40 galones, 12.5 litros. A partir de HU-05, los cambios se registrarán como movimientos auditados.</p>
                @error('cantidad_actual')<p class="s3-status s3-danger">{{ $message }}</p>@enderror</div>
        </div>
        <div class="s3-actions"><button class="btn btn-primary" type="submit">Guardar insumo</button>
            <a class="btn btn-secondary" href="{{ route('inventario.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
