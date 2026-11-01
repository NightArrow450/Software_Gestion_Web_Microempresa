@if(isset($historialPreparacion) && $historialPreparacion->isNotEmpty())
<section class="form-section"><h2>Historial de preparación e incidencias</h2><p>Cada registro conserva las unidades preparadas en ese momento y su responsable. No son entradas de inventario; no se suman como producción.</p>
<div class="table-container"><table class="app-table"><thead><tr><th>Fecha</th><th>SKU</th><th>Preparadas por despachar en ese momento</th><th>Incidencia / motivo</th><th>Responsable</th></tr></thead><tbody>
@foreach($historialPreparacion as $registro)<tr><td>{{ $registro->created_at }}</td><td>{{ $registro->codigo_sku }}</td><td>{{ $registro->cantidad }}</td><td>{{ \App\Services\AtenderPedidoUnidades::INCIDENCIAS[$registro->incidencia] ?? 'Sin incidencia registrada' }} · {{ $registro->motivo }}</td><td>{{ $registro->first_name }} {{ $registro->last_name }}</td></tr>@endforeach
</tbody></table></div>{{ $historialPreparacion->links() }}</section>
@endif
