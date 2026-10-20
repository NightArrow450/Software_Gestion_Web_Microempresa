@extends('layouts.app')
@section('title', 'Lista general de preparación')
@section('page-title', 'Lista general de preparación')
@section('breadcrumb', 'Inicio / Cumplimiento / Lista general')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint4.css') }}">@endpush

@section('content')
    <div class="cumplimiento-encabezado">
        <div>
            <div class="eyebrow">Preparación, despacho y entrega</div>
            <h1>Lista general de preparación</h1>
            <p class="muted">Productos de los pedidos confirmados o en preparación.</p>
        </div>
        <a href="{{ route('cumplimiento.index') }}" class="btn btn-secondary">Volver a cumplimiento</a>
    </div>

    <div class="cumplimiento-nota" role="note">
        Esta lista usa las <strong>cantidades solicitadas</strong> de los pedidos pendientes.
        El sistema todavía no registra unidades parcialmente preparadas por producto.
        Para empaques, el total de unidades se calcula con las unidades por empaque del
        catálogo actual; si no existe ese dato, se indica «No disponible».
    </div>

    <section class="cumplimiento-tarjeta">
        <div class="table-container">
            <table class="cumplimiento-tabla">
                <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col">Presentación y modalidad</th>
                        <th scope="col">Cantidad solicitada</th>
                        <th scope="col">Total de unidades</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($filas as $fila)
                    <tr>
                        <td>
                            <strong>{{ $fila['producto'] }}</strong>
                            <small class="cumplimiento-sku">SKU: {{ $fila['sku'] }}</small>
                        </td>
                        <td>{{ $fila['presentacion'] }} · {{ $fila['modalidad'] === 'empaque' ? 'Empaque' : 'Unidad' }}@if($fila['modalidad'] === 'empaque' && $fila['por_empaque']) de {{ $fila['por_empaque'] }} @endif</td>
                        <td>{{ $fila['cantidad'] }} {{ $fila['modalidad'] === 'empaque' ? ($fila['cantidad'] === 1 ? 'empaque' : 'empaques') : ($fila['cantidad'] === 1 ? 'unidad' : 'unidades') }}</td>
                        <td>
                            @if($fila['total_unidades'] === null)
                                <span class="cumplimiento-sin-dato">No disponible</span>
                            @else
                                <strong>{{ $fila['total_unidades'] }}</strong>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">No hay productos pendientes de preparación.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
