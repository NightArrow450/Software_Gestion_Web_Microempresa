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
        <a href="{{ route('cumplimiento.index') }}" class="btn btn-secondary cumplimiento-no-imprimir">Volver a cumplimiento</a>
    </div>

    <section class="cumplimiento-tarjeta cumplimiento-no-imprimir" aria-label="Filtros de entrega">
        <form method="GET" action="{{ route('cumplimiento.lista-preparacion') }}" class="cumplimiento-filtros">
            <div>
                <label for="fecha_desde"><strong>Entrega desde (opcional)</strong></label>
                <input class="form-control" type="date" name="fecha_desde" id="fecha_desde"
                       value="{{ $fechaDesde }}">
            </div>
            <div>
                <label for="fecha_hasta"><strong>Entrega hasta (opcional)</strong></label>
                <input class="form-control" type="date" name="fecha_hasta" id="fecha_hasta"
                       min="{{ $fechaDesde }}" value="{{ $fechaHasta }}">
            </div>
            <div>
                <label for="produccion_desde"><strong>Envasados desde</strong></label>
                <input class="form-control" type="date" name="produccion_desde" id="produccion_desde" value="{{ $produccionDesde }}" required>
            </div>
            <div>
                <label for="produccion_hasta"><strong>Envasados hasta</strong></label>
                <input class="form-control" type="date" name="produccion_hasta" id="produccion_hasta" value="{{ $produccionHasta }}" required>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar cantidades</button>
            <button type="button" class="btn btn-secondary" onclick="window.print()">Imprimir</button>
        </form>
        @if($errors->any())
            <div role="alert" class="cumplimiento-error">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
    </section>
    <p class="cumplimiento-rango">Entrega: @if($fechaDesde) desde <strong>{{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }}</strong> @else sin límite inicial (incluye atrasados) @endif
        @if($fechaHasta) hasta <strong>{{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}</strong> @else sin límite final @endif.
        Vista diaria predeterminada: atrasados y entregas de hoy. Amplía «Entrega hasta» para incluir futuros; borra ambos límites para consultar todos los pendientes.
    </p>

    @if($surtidosPendientes->isNotEmpty())
    <section class="cumplimiento-nota" role="alert"><h2>Preparación pendiente de composición surtida</h2><p>No se puede declarar cobertura completa: hay unidades comerciales sin distribuir por aroma. Las filas por SKU incluyen únicamente composiciones definidas.</p>
    <ul>@foreach($surtidosPendientes as $pendiente)<li>Pedido {{ $pendiente['pedido'] }} · {{ $pendiente['descripcion'] }} · {{ $pendiente['paquetes'] }} paquete(s), {{ $pendiente['unidades'] }} unidades pendientes de distribuir.</li>@endforeach</ul></section>
    @endif
    <div class="cumplimiento-nota" role="note">
        Esta lista usa las <strong>cantidades solicitadas</strong> de los pedidos pendientes.
        El stock es general y no está reservado. Los envasados registrados ya están incluidos en el stock: no se suman otra vez.
        La cobertura se calcula por SKU; disponer de stock no significa haber registrado un envasado ni preparado un pedido.
        El orden por producto y capacidad es orientativo, sin bloqueos.
        Se suman las unidades físicas del mismo producto, variante y presentación.
        Para empaques se usa el tamaño histórico de cada línea; solo las líneas antiguas sin ese dato
        usan el catálogo actual como respaldo. Si no puede calcularse el total, se indica «No disponible».
        Consulta «Ver detalle» para el armado de los empaques.
    </div>
    <p class="cumplimiento-rango">Envasado neto registrado del <strong>{{ \Carbon\Carbon::parse($produccionDesde)->format('d/m/Y') }}</strong>
        al <strong>{{ \Carbon\Carbon::parse($produccionHasta)->format('d/m/Y') }}</strong> (hora de Lima),
        incluyendo producción general sin pedido y descontando las correcciones actuales de esos registros.</p>
    @if(session('success'))<p role="status" class="cumplimiento-nota">{{ session('success') }}</p>@endif

    <section class="cumplimiento-tarjeta">
        <div class="table-container">
            <table class="cumplimiento-tabla cumplimiento-lista">
                <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col">Presentación</th>
                        <th scope="col">Unidades solicitadas pendientes</th>
                        <th scope="col">Stock disponible</th>
                        <th scope="col">Envasado neto registrado</th>
                        <th scope="col">Falta cubrir / cobertura</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($filas as $fila)
                    @if($loop->first || $filas[$loop->index - 1]['producto_clave'] !== $fila['producto_clave'])
                        <tr class="cumplimiento-grupo-producto"><th colspan="6" scope="rowgroup">{{ $fila['producto'] }}</th></tr>
                    @endif
                    <tr>
                        <td data-label="Producto">
                            <strong>{{ $fila['producto'] }}</strong>
                            @if($fila['variante'])<small class="cumplimiento-sku">Variante: {{ $fila['variante'] }}</small>@endif
                            <small class="cumplimiento-sku">SKU: {{ implode(', ', $fila['skus']) }}</small>
                            <details class="cumplimiento-desglose">
                                <summary>Ver detalle de empaques y unidades</summary>
                                <ul>
                                    @foreach($fila['desglose'] as $detalle)
                                        <li>
                                            {{ $detalle['cantidad'] }} {{ $detalle['modalidad'] === 'empaque' ? ($detalle['cantidad'] === 1 ? 'empaque' : 'empaques') : ($detalle['cantidad'] === 1 ? 'unidad' : 'unidades') }}@if($detalle['modalidad'] === 'empaque') de {{ $detalle['por_empaque'] ?? 'tamaño no disponible' }}@endif
                                            = {{ $detalle['total_unidades'] === null ? 'No disponible' : $detalle['total_unidades'].' unidades' }}
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        </td>
                        <td data-label="Presentación">{{ $fila['presentacion'] }}@if($fila['envase'])<small class="cumplimiento-sku">{{ $fila['envase'] }}</small>@endif</td>
                        <td data-label="Unidades solicitadas pendientes">
                            @if($fila['total_unidades'] === null)
                                <span class="cumplimiento-sin-dato">No disponible</span>
                            @else
                                <strong>{{ $fila['total_unidades'] }}</strong>
                            @endif
                        </td>
                        <td data-label="Stock disponible">{{ $fila['presentaciones'] === [] ? 'No disponible' : $fila['stock_disponible'] }}</td>
                        <td data-label="Envasado neto registrado">{{ $fila['envasadas'] }}<small class="cumplimiento-sku">{{ $fila['vinculadas'] }} vinculadas a pedidos de esta consulta. Incluidas en el total.</small></td>
                        <td data-label="Falta cubrir / cobertura">
                            <strong>{{ $fila['faltante'] ?? 'No disponible' }}</strong>
                            <small class="cumplimiento-sku">{{ $fila['cobertura'] }}</small>
                            @foreach($fila['presentaciones'] as $presentacion)
                                <div class="cumplimiento-presentacion">
                                    <small>{{ $presentacion['sku'] }}: solicitadas {{ $presentacion['solicitadas'] ?? 'No disponible' }}, stock {{ $presentacion['stock'] }}, envasadas {{ $presentacion['envasadas'] }}, faltan {{ $presentacion['faltante'] ?? 'No disponible' }}.</small>
                                    @if($puedeRegistrar && $presentacion['activa'])
                                        <button type="button" class="btn btn-primary cumplimiento-no-imprimir" data-registrar-envasado="{{ $presentacion['id'] }}" data-sku="{{ $presentacion['sku'] }}" aria-haspopup="dialog" aria-controls="envasado-integrado">Registrar envasado</button>
                                    @elseif($puedeRegistrar)
                                        <small>Presentación inactiva: no admite nuevos envasados.</small>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay productos pendientes de preparación.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @if($puedeRegistrar)
        @include('cumplimiento.partials.envasado-integrado')
    @endif
@endsection

@push('scripts')
@if($puedeRegistrar)<script src="{{ asset('js/lista-preparacion-envasado.js') }}" defer></script>@endif
<script>
(() => {
    const detalles = Array.from(document.querySelectorAll('.cumplimiento-desglose'));
    let abiertos = null;
    window.addEventListener('beforeprint', () => {
        abiertos ??= detalles.map(detalle => detalle.open);
        detalles.forEach(detalle => detalle.open = true);
    });
    window.addEventListener('afterprint', () => {
        if (abiertos === null) return;
        detalles.forEach((detalle, indice) => detalle.open = abiertos[indice]);
        abiertos = null;
    });
})();
</script>
@endpush
