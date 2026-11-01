@extends('layouts.app')
@section('title','Confirmar corrección de envasado')
@section('page-title','Confirmar corrección de envasado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/envasado.css') }}">@endpush
@section('content')
<section class="envasado-seccion"><h1>Confirmar compensación</h1><p>{{ $plan['datos']['clasificacion']==='error_administrativo' ? 'Error administrativo' : 'Pérdida física' }} · Se revertirán {{ $plan['cantidad'] }} unidades terminadas. Stock actual: {{ $plan['inventario']->cantidad_actual }}.</p><p>{{ $plan['datos']['motivo'] }}</p>
@if($plan['materiales'] !== [])<div class="table-container"><table><thead><tr><th>Material histórico</th><th>Consumo afectado</th><th>Devolución declarada</th><th>No devuelto</th></tr></thead><tbody>@foreach($plan['materiales'] as $material)<tr><td>{{ $material['consumo']->nombre }}</td><td>{{ \App\Services\CantidadesEnvasado::decimal($material['afectada']) }}</td><td>{{ \App\Services\CantidadesEnvasado::decimal($material['devuelta']) }}</td><td>{{ \App\Services\CantidadesEnvasado::decimal($material['afectada']-$material['devuelta']) }}</td></tr>@endforeach</tbody></table></div>
<p>Solo se devuelven las cantidades declaradas. En pérdida física, lo no recuperado queda registrado como pérdida. La operación conserva los movimientos originales.</p>@else<p>Se compensan únicamente unidades terminadas. No se devolverán ni descontarán materiales.</p>@endif
<form method="POST" action="{{ route('envasado.correcciones.store',$plan['envasado']) }}">@csrf @include('components.token-operacion')
@foreach(['clasificacion','cantidad','motivo','revertidas_previas'] as $campo)<input type="hidden" name="{{ $campo }}" value="{{ $plan['datos'][$campo] }}">@endforeach
@foreach($plan['datos']['devoluciones'] as $id=>$cantidad)<input type="hidden" name="devoluciones[{{ $id }}]" value="{{ $cantidad }}">@endforeach
<label><input type="checkbox" required> Confirmo las unidades a revertir y las compensaciones indicadas, conservando los movimientos originales.</label><button class="btn btn-primary">Confirmar compensación de inventario</button><a class="btn btn-secondary" href="{{ route('envasado.show',$plan['envasado']) }}">Volver sin aplicar</a></form></section>
@endsection
