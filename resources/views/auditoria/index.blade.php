@extends('layouts.app')
@section('title', 'Auditoría')
@section('page-title', 'Auditoría')
@section('content')
<div class="page-heading"><div><h1>Auditoría</h1><p>Consulta de acciones sensibles. Los registros no pueden modificarse.</p></div></div>
<form method="GET" class="form-grid">
@foreach(['usuario_id' => 'Usuario (ID)', 'accion' => 'Acción', 'entidad' => 'Entidad', 'desde' => 'Desde', 'hasta' => 'Hasta'] as $campo => $etiqueta)
<label>{{ $etiqueta }}<input class="form-control" name="{{ $campo }}" value="{{ request($campo) }}" type="{{ in_array($campo, ['desde', 'hasta']) ? 'date' : 'text' }}"></label>
@endforeach
<button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-secondary" href="{{ route('auditoria.index') }}">Limpiar filtros</a>
</form>
<div class="table-container"><table><thead><tr><th>Fecha (Lima)</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalle</th><th>IP</th></tr></thead><tbody>
@forelse($registros as $registro)
<tr><td>{{ $registro->created_at->timezone('America/Lima')->format('d/m/Y H:i:s') }}</td><td>{{ $registro->usuario_id }}</td><td>{{ $registro->accion }}</td><td>{{ $registro->entidad }} #{{ $registro->entidad_id }}</td><td><details><summary>Ver detalle</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ json_encode($registro->detalle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></td><td>{{ $registro->ip }}</td></tr>
@empty<tr><td colspan="6">No hay acciones para los filtros seleccionados.</td></tr>@endforelse
</tbody></table></div>
{{ $registros->links() }}
@endsection
