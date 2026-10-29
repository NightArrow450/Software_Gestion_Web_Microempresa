@extends('layouts.app')
@section('title', 'Archivo de modalidades surtidas')
@section('page-title', 'Archivo de modalidades surtidas')
@section('content')
<div class="page-heading">
    <div><h1>Archivo de modalidades surtidas</h1>
        <p>Consulta de formatos anteriores. La composición de aromas histórica se conserva en cada pedido.</p>
        <p>Los nuevos pedidos se venden por unidades físicas; su agrupación opcional se define directamente en el pedido.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('productos.index') }}">Volver al catálogo</a>
</div>
<div class="table-container">
    <table>
        <thead><tr><th>Formato histórico</th><th>Producto</th><th>Unidades del paquete histórico</th><th>Precio histórico de catálogo</th><th>SKU autorizados</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse($formatos as $formato)
            <tr><td>{{ $formato->nombre }}</td><td>{{ $formato->producto_nombre ?? 'Producto histórico' }}</td>
                <td>{{ $formato->unidades_por_empaque }}</td><td>S/ {{ number_format((float)$formato->precio_empaque, 2) }}</td>
                <td>{{ $autorizados[$formato->id] ?? 0 }}</td><td>{{ $formato->estado ? 'Activo en el modelo anterior' : 'Inactivo' }}</td></tr>
        @empty
            <tr><td colspan="6">No hay formatos históricos registrados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $formatos->links() }}
@endsection
