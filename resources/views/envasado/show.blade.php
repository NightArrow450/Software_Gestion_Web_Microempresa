@extends('layouts.app')
@section('title','Envasado')
@section('page-title','Envasado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/envasado.css') }}">@endpush
@section('content')
<section class="envasado-seccion"><h1>Envasado {{ $envasado->numero }}</h1><p>{{ $envasado->presentacion_historica['producto'] }} · {{ $envasado->presentacion_historica['sku'] }} · {{ $envasado->configuracion_nombre }}</p>
<p>Responsable: {{ $envasado->usuario->first_name }} {{ $envasado->usuario->last_name }}. Fecha (Lima): {{ $envasado->created_at->timezone('America/Lima')->format('d/m/Y H:i') }}.</p>
<p>Unidades solicitadas del pedido para este SKU: {{ $solicitadas ?? 'Sin pedido' }}. Unidades registradas en este envasado: {{ $envasado->cantidad }}. Revertidas: {{ $envasado->unidades_revertidas }}. Producción neta: {{ $envasado->cantidad - $envasado->unidades_revertidas }}.</p><p>Stock disponible general: {{ $stock }}. Vincular un pedido no reserva unidades ni identifica a qué envasado pertenecen los productos despachados.</p><p>Estado: {{ $envasado->estado }}. {{ $envasado->observaciones }}</p>
<div class="table-container"><table><thead><tr><th>Material realmente usado</th><th>Consumo por unidad</th><th>Total descontado original</th></tr></thead><tbody>@foreach($envasado->consumos as $consumo)<tr><td>{{ $consumo->nombre }}</td><td>{{ $consumo->cantidad_por_unidad }}</td><td>{{ $consumo->cantidad }} {{ $consumo->unidad_medida }}</td></tr>@endforeach</tbody></table></div>
<a class="btn btn-secondary" href="{{ route('envasado.index') }}">Volver al historial</a>
</section>
@endsection
