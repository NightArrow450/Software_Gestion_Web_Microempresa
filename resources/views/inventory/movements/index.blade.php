@extends('layouts.app')
@section('title', 'Movimientos de inventario')
@section('page-title', 'Movimientos de inventario')
@section('breadcrumb', 'Inicio / Inventario / Movimientos')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint3.css') }}">@endpush
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">Inventario · HU-05</div><h1>Movimientos de inventario</h1>
        <p>Historial auditado de productos terminados e insumos: entradas, salidas y ajustes.</p></div>
    <div class="s3-actions">
        <a class="btn btn-secondary" href="{{ route('inventario.index') }}">Consultar stock</a>
        @if($puedeRegistrar)<a class="btn btn-primary" href="{{ route('inventario.movimientos.create') }}">+ Nuevo movimiento</a>@endif
    </div>
</div>
<section class="filters"><form method="GET" action="{{ route('inventario.movimientos.index') }}">
    <div class="filters-grid">
        <div class="form-group"><label class="form-label" for="buscar">Producto, insumo o código</label>
            <input class="form-control" name="buscar" id="buscar" value="{{ request('buscar') }}"></div>
        <div class="form-group"><label class="form-label" for="tipo">Tipo de movimiento</label>
            <select class="form-control" name="tipo" id="tipo"><option value="">Todos</option>
                @foreach(['entrada'=>'Entrada','salida'=>'Salida','ajuste'=>'Ajuste'] as $valor=>$titulo)
                    <option value="{{ $valor }}" @selected(request('tipo')===$valor)>{{ $titulo }}</option>
                @endforeach</select></div>
        <div class="form-group"><label class="form-label" for="fecha">Fecha</label>
            <input class="form-control" type="date" id="fecha" name="fecha" value="{{ request('fecha') }}"></div>
    </div>
    <div class="s3-actions"><button class="btn btn-primary" type="submit">Filtrar</button>
        <a class="btn btn-secondary" href="{{ route('inventario.movimientos.index') }}">Limpiar</a></div>
</form></section>
<section class="table-card"><div class="table-header"><strong>Historial de movimientos</strong><span class="muted">{{ $movimientos->total() }} resultado(s)</span></div>
    <div class="table-container"><table class="app-table"><thead><tr>
        <th>Fecha y hora</th><th>Clase</th><th>Código</th><th>Producto / Insumo</th><th>Tipo</th><th>Cambio</th>
        <th>Antes</th><th>Después</th><th>Responsable</th><th>Motivo</th>
    </tr></thead><tbody>
    @forelse($movimientos as $m)
        <tr><td>{{ $m->created_at?->timezone('America/Lima')->format('d/m/Y H:i') }}</td>
        <td>{{ $m->insumo_id ? 'Insumo' : 'Producto' }}</td>
        <td>{{ $m->insumo?->codigo ?? $m->presentacion?->codigo_sku }}</td>
        <td>{{ $m->insumo?->nombre ?? $m->presentacion?->producto?->nombre }}</td>
        <td>{{ ucfirst($m->tipo) }}</td>
        <td>{{ $m->tipo==='salida' ? '-' : ($m->tipo==='entrada' ? '+' : '±') }}{{ $m->cantidad }}</td>
        <td>{{ $m->stock_anterior }}</td><td><strong>{{ $m->stock_nuevo }}</strong></td>
        <td>{{ $m->usuario?->first_name }} {{ $m->usuario?->last_name }}</td>
        <td>{{ $m->motivo }}</td></tr>
    @empty<tr><td colspan="10">No se han registrado movimientos.</td></tr>@endforelse
    </tbody></table></div><div class="s3-pagination">{{ $movimientos->links() }}</div>
</section>
@endsection
