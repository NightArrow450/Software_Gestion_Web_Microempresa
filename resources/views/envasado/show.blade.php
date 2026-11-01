@extends('layouts.app')
@section('title','Envasado')
@section('page-title','Envasado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/envasado.css') }}">@endpush
@section('content')
<section class="envasado-seccion"><h1>Envasado {{ $envasado->numero }}</h1><p>{{ $envasado->presentacion_historica['producto'] }} · {{ $envasado->presentacion_historica['sku'] }} · {{ $envasado->configuracion_nombre }}</p>
<p>Responsable: {{ $envasado->usuario->first_name }} {{ $envasado->usuario->last_name }}. Fecha (Lima): {{ $envasado->created_at->timezone('America/Lima')->format('d/m/Y H:i') }}.</p>
<p>Unidades solicitadas del pedido para este SKU: {{ $solicitadas ?? 'Sin pedido' }}. Unidades registradas en este envasado: {{ $envasado->cantidad }}. Revertidas: {{ $envasado->unidades_revertidas }}. Producción neta: {{ $envasado->cantidad - $envasado->unidades_revertidas }}.</p><p>Stock disponible general: {{ $stock }}. Vincular un pedido no reserva unidades ni identifica a qué envasado pertenecen los productos despachados.</p><p>Estado: {{ $envasado->estado }}. {{ $envasado->observaciones }}</p>
@if($netoAcumulado !== null)<p>Total neto acumulado de envasados vinculados a este pedido y presentación: {{ $netoAcumulado }} unidades.</p>@endif
@if($envasado->consumos->isNotEmpty())<div class="table-container"><table><thead><tr><th>Material realmente usado</th><th>Consumo por unidad</th><th>Total descontado original</th></tr></thead><tbody>@foreach($envasado->consumos as $consumo)<tr><td>{{ $consumo->nombre }}</td><td>{{ $consumo->cantidad_por_unidad }}</td><td>{{ $consumo->cantidad }} {{ $consumo->unidad_medida }}</td></tr>@endforeach</tbody></table></div>@else<p>Este envasado registró únicamente unidades terminadas, sin descontar materiales.</p>@endif
<a class="btn btn-secondary" href="{{ route('envasado.index') }}">Volver al historial</a>
</section>

@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="envasado-seccion"><h2>Historial de correcciones</h2>
@forelse($envasado->correcciones as $correccion)<article><h3>{{ $correccion->cantidad }} unidades · {{ $correccion->clasificacion === 'error_administrativo' ? 'Error administrativo' : 'Pérdida física' }}</h3><p>{{ $correccion->created_at->timezone('America/Lima')->format('d/m/Y H:i') }} · {{ $correccion->usuario->first_name }} {{ $correccion->usuario->last_name }} · {{ $correccion->motivo }}</p>
@foreach($correccion->materiales as $material) @php $consumo=$envasado->consumos->firstWhere('id',$material->envasado_consumo_id); @endphp <p>{{ $consumo?->nombre }}: devuelto {{ $material->cantidad_devuelta }}, pérdida física {{ $material->cantidad_perdida }} {{ $consumo?->unidad_medida }}.</p>@endforeach</article>@empty<p>No hay correcciones.</p>@endforelse
</section>
@if(auth()->user()->role?->tienePermiso('envasado.corregir') && $envasado->estado !== 'anulado')
<section class="form-section envasado-seccion"><h2>Corregir o anular unidades</h2><p>Para anular el saldo restante, indica {{ $envasado->cantidad-$envasado->unidades_revertidas }} unidades. Para aumentar producción registra un envasado adicional. No hay plazo automático de corrección.</p>
<form method="POST" action="{{ route('envasado.correcciones.previsualizar',$envasado) }}">@csrf
<input type="hidden" name="revertidas_previas" value="{{ $envasado->unidades_revertidas }}">
<label>Clasificación obligatoria<select class="form-control" name="clasificacion" required><option value="">Selecciona</option><option value="error_administrativo">Error administrativo: cantidad registrada incorrectamente</option><option value="perdida_fisica">Pérdida física de producto terminado</option></select></label>
<label>Unidades terminadas que se deben revertir<input class="form-control" required type="number" min="1" max="{{ $envasado->cantidad-$envasado->unidades_revertidas }}" step="1" name="cantidad"></label>
@if($envasado->consumos->isNotEmpty())<p>Declara únicamente materiales que realmente no se usaron o se recuperaron. Cero significa que no se devuelve ese material. No se devuelven automáticamente todos los materiales.</p>
@endif
@foreach($envasado->consumos as $consumo)<label>{{ $consumo->nombre }}: cantidad a devolver ({{ $consumo->unidad_medida }}; consumo original por unidad {{ $consumo->cantidad_por_unidad }})<input class="form-control" type="number" min="0" max="{{ $consumo->cantidad }}" step="{{ $consumo->unidad_medida==='unidad' ? '1' : '0.001' }}" required name="devoluciones[{{ $consumo->id }}]" value="0"></label>@endforeach
<label>Motivo y detalle de lo no utilizado o recuperado<textarea class="form-control" name="motivo" minlength="10" maxlength="1000" required></textarea></label><button class="btn btn-primary">Revisar compensación antes de confirmar</button></form></section>
@endif
<section class="envasado-seccion"><h2>Reportes de errores</h2>@forelse($envasado->reportes as $reporte)<p>{{ $reporte->created_at->timezone('America/Lima')->format('d/m/Y H:i') }} · {{ $reporte->usuario->first_name }} {{ $reporte->usuario->last_name }}: {{ $reporte->motivo }}</p>@empty<p>No hay reportes.</p>@endforelse
@if(auth()->user()->role?->tienePermiso('envasado.reportar'))<form method="POST" action="{{ route('envasado.reportar',$envasado) }}">@csrf @include('components.token-operacion')<label>Comunicar error (no modifica inventario)<textarea class="form-control" required name="motivo" minlength="10" maxlength="1000"></textarea></label><button class="btn btn-secondary">Comunicar error</button></form>@endif</section>
@endsection
