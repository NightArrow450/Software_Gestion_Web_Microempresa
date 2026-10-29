@if($versionesPedido !== null)
<section class="form-section"><h2>Historial de modificaciones comerciales</h2>
<p>Una versión nueva no modifica los hechos físicos de despacho, entrega ni retornos.</p>
@foreach($versionesPedido as $v)
<details><summary>Versión {{ $v->numero }} · {{ $v->created_at }} · {{ $v->first_name }} {{ $v->last_name }}</summary>
<p>Motivo: {{ $v->motivo }}</p>
@php($datosVersion = json_decode($v->datos, true, 512, JSON_THROW_ON_ERROR))
<div class="table-container"><table><thead><tr><th>Producto y SKU</th><th>Unidades vigentes en esta versión</th><th>En espera</th><th>Precio unitario</th><th>Importe</th></tr></thead><tbody>
@foreach($datosVersion['lineas'] as $lineaVersion)<tr><td>{{ $lineaVersion['descripcion_producto'] }} · {{ $lineaVersion['codigo_sku'] }}</td><td>{{ $lineaVersion['cantidad'] }}</td><td>{{ $lineaVersion['cantidad_espera'] ?? 0 }}</td><td>{{ $lineaVersion['precio_unitario'] }}</td><td>{{ $lineaVersion['importe'] }}</td></tr>@endforeach
</tbody></table></div>
<p>Total de esa versión: S/ {{ $datosVersion['cabecera']['total'] }}</p>
</details>
@endforeach
{{ $versionesPedido->links() }}
</section>
@endif
