@extends('layouts.app')
@section('title','Envasado')
@section('page-title','Envasado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/envasado.css') }}">@endpush
@section('content')
<div class="page-heading"><div><h1>Envasados reales</h1><p>La producción registrada aumenta el stock general. Vincular un pedido no reserva unidades para un cliente.</p></div>@if(auth()->user()->role?->tienePermiso('envasado.registrar'))<a class="btn btn-primary" href="{{ route('envasado.create') }}">Registrar envasado</a>@endif</div>
<form class="envasado-grid" method="GET"><label>Pedido (ID)<input class="form-control" type="number" min="1" name="pedido_id" value="{{ request('pedido_id') }}"></label><label>Desde<input class="form-control" type="date" name="desde" value="{{ request('desde') }}"></label><label>Hasta<input class="form-control" type="date" name="hasta" value="{{ request('hasta') }}"></label><button class="btn btn-secondary">Filtrar</button></form>
<section class="envasado-seccion"><div class="table-container"><table><thead><tr><th>Fecha (Lima)</th><th>Presentación</th><th>Unidades registradas</th><th>Unidades revertidas</th><th>Pedido</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
@forelse($registros as $registro)<tr><td>{{ $registro->created_at->timezone('America/Lima')->format('d/m/Y H:i') }}</td><td>{{ $registro->presentacion_historica['producto'] }} · {{ $registro->presentacion_historica['sku'] }}</td><td>{{ $registro->cantidad }}</td><td>{{ $registro->unidades_revertidas }}</td><td>{{ $registro->pedido?->numero ?? 'Anticipado' }}</td><td>{{ $registro->estado }}</td><td><a href="{{ route('envasado.show',$registro) }}">Ver envasado</a></td></tr>@empty<tr><td colspan="7">No hay envasados registrados.</td></tr>@endforelse
</tbody></table></div>{{ $registros->links() }}</section>
@endsection
