<section class="envasado-confirmacion" tabindex="-1" data-confirmacion-envasado><h1>Confirmar envasado</h1><p>{{ $plan['presentacion']->codigo_sku }} · {{ $plan['configuracion']->nombre }} · {{ $plan['cantidad'] }} unidades realmente envasadas.</p><p>Stock disponible actual: {{ $plan['inventario']->cantidad_actual }}. Stock después de confirmar: {{ $plan['inventario']->cantidad_actual + $plan['cantidad'] }}.</p><p>Pedido: {{ $plan['pedido']?->numero ?? 'Sin pedido (anticipado)' }}. Este vínculo no reserva inventario.</p>
<div class="table-container"><table><thead><tr><th>Material utilizado</th><th>Consumo</th><th>Disponible</th></tr></thead><tbody>@foreach($plan['consumos'] as $consumo)<tr><td>{{ $consumo['insumo']->nombre }}</td><td>{{ \App\Services\CantidadesEnvasado::decimal($consumo['milesimas']) }} {{ $consumo['insumo']->unidad_medida }}</td><td>{{ $consumo['insumo']->cantidad_actual }}</td></tr>@endforeach</tbody></table></div>
<p>La consulta no modificó existencias. Al confirmar, el servidor volverá a comprobar configuración y stock. Los materiales por empaque, incluidas mangas, no se descuentan en esta operación.</p>
<form method="POST" action="{{ route('envasado.store') }}">@csrf @include('components.token-operacion')
@if($integrado ?? false)
    <input type="hidden" name="desde_lista" value="1">
    @foreach($filtrosLista as $campo=>$valor)
        <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
    @endforeach
@endif
@foreach(['presentacion_producto_id'=>$plan['presentacion']->id,'configuracion_envasado_id'=>$plan['configuracion']->id,'version_configuracion'=>$plan['configuracion']->version,'cantidad'=>$plan['cantidad'],'pedido_id'=>$plan['pedido']?->id,'observaciones'=>$plan['observaciones']] as $campo=>$valor)<input type="hidden" name="{{ $campo }}" value="{{ $valor }}">@endforeach
<label><input type="checkbox" required> Confirmo que esta producción es real y no fue ingresada manualmente.</label><button type="submit" class="btn btn-primary">Confirmar consumo e ingreso</button>@if($integrado ?? false)<button type="button" class="btn btn-secondary" data-editar-envasado>Volver a editar</button>@else<a class="btn btn-secondary" href="{{ route('envasado.create') }}">Volver sin registrar</a>@endif</form></section>
