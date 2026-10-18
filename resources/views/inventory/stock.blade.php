@extends('layouts.app')
@section('title', 'Stock y alertas')
@section('page-title', 'Stock y alertas')
@section('breadcrumb', 'Inicio / Inventario / Alertas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sprint3.css') }}">@endpush
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">Inventario · HU-06</div><h1>Control de stock y alertas</h1>
        <p>Alertas para productos terminados y para insumos: botellas, galones, etiquetas, tapas y materias primas.</p></div>
    <a class="btn btn-secondary" href="{{ route('inventario.index') }}">Consultar disponibilidad</a>
</div>
<div class="s3-cardrow">
    <div class="s3-metric"><strong>{{ $resumen['agotado'] }}</strong><span>Agotados: cantidad 0</span></div>
    <div class="s3-metric"><strong>{{ $resumen['bajo'] }}</strong><span>Stock bajo: hasta el mínimo</span></div>
    <div class="s3-metric"><strong>{{ $resumen['normal'] }}</strong><span>Stock normal: encima del mínimo</span></div>
</div>
<section class="filters"><form method="GET" action="{{ route('inventario.stock') }}">
    <div class="filters-grid">
        <div class="form-group"><label class="form-label" for="buscar">Nombre o código</label>
            <input class="form-control" name="buscar" id="buscar" value="{{ request('buscar') }}" placeholder="Producto, SKU, botella, galón, etiqueta..."></div>
        <div class="form-group"><label class="form-label" for="filtro">Estado de stock</label>
            <select class="form-control" name="filtro" id="filtro">
                <option value="">Todos</option>
                @foreach(['agotado'=>'Agotado','bajo'=>'Stock bajo','normal'=>'Normal'] as $clave=>$titulo)
                    <option value="{{ $clave }}" @selected($filtro===$clave)>{{ $titulo }}</option>
                @endforeach
            </select></div>
    </div><div class="s3-actions"><button class="btn btn-primary" type="submit">Aplicar filtro</button>
        <a class="btn btn-secondary" href="{{ route('inventario.stock') }}">Limpiar</a></div>
</form></section>
<section class="table-card"><div class="table-header"><strong>Productos terminados · mínimos</strong>
    <span class="muted">{{ $presentaciones->total() }} resultado(s)</span></div>
    <div class="table-container"><table class="app-table">
        <thead><tr><th>SKU</th><th>Producto</th><th>Stock actual</th><th>Stock mínimo</th><th>Alerta</th><th>Configurar mínimo</th></tr></thead>
        <tbody>@forelse($presentaciones as $p)
            @php $cantidad=(int)$p->stock_actual; $minimo=(int)$p->stock_minimo_configurado; @endphp
            <tr><td><strong>{{ $p->codigo_sku }}</strong></td>
                <td>{{ $p->producto?->nombre }} {{ $p->variante ? '· '.$p->variante->valor : '' }}
                    · {{ $p->contenido }} {{ $p->unidad_medida }}</td>
                <td><strong>{{ $cantidad }}</strong> u.</td><td>{{ $minimo }} u.</td>
                <td>@if($cantidad === 0)<span class="s3-status s3-danger">Agotado</span>
                    @elseif($cantidad <= $minimo)<span class="s3-status s3-warning">Stock bajo</span>
                    @else<span class="s3-status s3-ok">Normal</span>@endif</td>
                <td>@if($puedeConfigurar)
                    <form class="s3-inline" method="POST" action="{{ route('inventario.stock.minimo', $p) }}">
                        @csrf @method('PATCH')
                        <input class="form-control" style="max-width:110px" aria-label="Mínimo para {{ $p->codigo_sku }}"
                            type="number" name="stock_minimo" min="0" max="1000000000" value="{{ $minimo }}" required>
                        <button type="submit" class="btn btn-secondary">Guardar</button>
                    </form>
                @else <span class="muted">Solo lectura</span> @endif</td></tr>
        @empty<tr><td colspan="6">No se encontraron productos terminados.</td></tr>@endforelse</tbody>
    </table></div><div class="s3-pagination">{{ $presentaciones->links() }}</div>
</section>
<section class="table-card s3-panel"><div class="table-header"><strong>Insumos, envases y materiales · mínimos</strong>
    <span class="muted">{{ $insumos->total() }} resultado(s)</span></div>
    <div class="table-container"><table class="app-table">
        <thead><tr><th>Código</th><th>Insumo</th><th>Categoría</th><th>Stock actual</th><th>Mínimo</th><th>Alerta</th><th>Configurar mínimo</th></tr></thead>
        <tbody>@forelse($insumos as $i)
            @php $cantidad = (float)$i->cantidad_actual; $minimo = (float)$i->stock_minimo; @endphp
            <tr><td><strong>{{ $i->codigo }}</strong></td><td>{{ $i->nombre }}</td><td>{{ $i->categoria }}</td>
                <td><strong>{{ number_format($cantidad, 3, '.', '') }}</strong> {{ $i->unidad_medida }}</td>
                <td>{{ number_format($minimo, 3, '.', '') }} {{ $i->unidad_medida }}</td>
                <td>@if($cantidad == 0)<span class="s3-status s3-danger">Agotado</span>
                    @elseif($cantidad <= $minimo)<span class="s3-status s3-warning">Stock bajo</span>
                    @else<span class="s3-status s3-ok">Normal</span>@endif</td>
                <td>@if($puedeConfigurar)
                    <form class="s3-inline" method="POST" action="{{ route('inventario.insumos.stock.minimo', $i) }}">
                        @csrf @method('PATCH')
                        <input class="form-control" style="max-width:110px" aria-label="Mínimo para {{ $i->codigo }}"
                            type="number" step="0.001" min="0" max="1000000000" name="stock_minimo" value="{{ $i->stock_minimo }}" required>
                        <button type="submit" class="btn btn-secondary">Guardar</button>
                    </form>
                @else <span class="muted">Solo lectura</span> @endif</td></tr>
        @empty<tr><td colspan="7">No hay insumos registrados. Puedes crearlos desde Disponibilidad.</td></tr>@endforelse</tbody>
    </table></div><div class="s3-pagination">{{ $insumos->links() }}</div>
</section>
<p class="s3-muted">Se utiliza un mínimo inicial de 10 para nuevos SKU o insumos. Ajusta el umbral según la cantidad que la empresa necesite conservar. La alerta no realiza compras automáticamente.</p>
@endsection
