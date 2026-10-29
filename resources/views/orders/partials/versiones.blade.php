@if($versionesPedido !== null)
<section class="form-section"><h2>Historial de modificaciones comerciales</h2>
@if(!empty($cambiosOriginales))
<aside aria-label="Cambios respecto a la solicitud original"><h3>Cambios respecto a la solicitud original</h3>
<p>Reparto debe explicar las sustituciones y cantidades vigentes al cliente. Esta información no presupone su aceptación; registra lo que realmente reciba o rechace.</p>
<ul>@foreach($cambiosOriginales as $cambioOriginal)<li>{{ $cambioOriginal['descripcion'] }}: original {{ $cambioOriginal['original'] }} {{ $cambioOriginal['modalidad'] }}, vigente {{ $cambioOriginal['vigente'] }} {{ $cambioOriginal['modalidad'] }}.</li>@endforeach</ul>
<p>Consulta debajo los motivos, responsables e importes de cada versión.</p></aside>
@endif
<p>Una versión nueva no modifica los hechos físicos de despacho, entrega ni retornos.</p>
@foreach($versionesPedido as $v)
<details><summary>Versión {{ $v->numero }} · {{ $v->created_at }} · {{ $v->first_name }} {{ $v->last_name }}</summary>
<p>Motivo: {{ $v->motivo }}</p>
@php($datosVersion = json_decode($v->datos, true, 512, JSON_THROW_ON_ERROR))
<div class="table-container"><table><thead><tr><th>Producto y SKU</th><th>Cantidad comercial y modalidad</th><th>En espera</th><th>Precio pactado</th><th>Importe</th></tr></thead><tbody>
@foreach($datosVersion['lineas'] as $lineaVersion)<tr><td>{{ $lineaVersion['descripcion_producto'] }} · {{ $lineaVersion['codigo_sku'] }}</td><td>{{ $lineaVersion['cantidad'] }} {{ $lineaVersion['modalidad'] }} @if(($lineaVersion['cantidad_fisica_ajustada'] ?? null) !== null) · Ajuste físico: {{ $lineaVersion['cantidad_fisica_ajustada'] }} unidades @endif</td><td>{{ $lineaVersion['cantidad_espera'] ?? 0 }}</td><td>{{ $lineaVersion['precio_unitario'] }}</td><td>{{ $lineaVersion['importe'] }}</td></tr>@endforeach
</tbody></table></div>
<p>Total de esa versión: S/ {{ $datosVersion['cabecera']['total'] }}</p>
</details>
@endforeach
{{ $versionesPedido->links() }}
</section>
@endif
