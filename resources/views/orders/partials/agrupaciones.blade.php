@if($pedido->modelo_comercial === 'unidades')
@php
    $fotoUnidades = $fotosUnidades[$pedido->id] ?? [];
@endphp
<section class="form-section" aria-label="Agrupaciones logísticas del pedido">
<h2>Forma de entrega y agrupaciones opcionales</h2>
<p>Todos los importes corresponden a unidades físicas. Los paquetes siguientes no generan otra venta ni otro inventario.</p>
@php
    $asignadasUnidades = [];
@endphp
@forelse(($fotoUnidades['agrupaciones'] ?? []) as $grupo)
<h3>Paquete {{ $loop->iteration }} · {{ $grupo['unidades'] }} de {{ $grupo['capacidad'] }} unidades de capacidad</h3>
@if($grupo['unidades'] < $grupo['capacidad'])<p>Paquete incompleto expresamente declarado.</p>@endif
<ul>@foreach($grupo['componentes'] as $c)
@php
    $lineaGrupo = collect($fotoUnidades['lineas'] ?? [])->firstWhere('id', $c['detalle_id']);
    $asignadasUnidades[$c['detalle_id']] = ($asignadasUnidades[$c['detalle_id']] ?? 0) + $c['cantidad'];
@endphp
<li>{{ $c['cantidad'] }} unidades de {{ $lineaGrupo['descripcion_producto'] ?? 'Referencia histórica' }} · {{ $lineaGrupo['codigo_sku'] ?? '' }}</li>
@endforeach</ul>
@empty<p>Entrega suelta. No se han definido paquetes.</p>@endforelse
<h3>Unidades sueltas y en espera</h3>
<ul>@foreach(($fotoUnidades['lineas'] ?? []) as $lineaGrupo)
@php
    $sueltasUnidades = $lineaGrupo['cantidad'] - ($asignadasUnidades[$lineaGrupo['id']] ?? 0);
@endphp
@if($sueltasUnidades > 0)<li>Sueltas: {{ $sueltasUnidades }} unidades de {{ $lineaGrupo['descripcion_producto'] }}.</li>@endif
@if(($lineaGrupo['cantidad_espera'] ?? 0) > 0)<li>En espera: {{ $lineaGrupo['cantidad_espera'] }} unidades de {{ $lineaGrupo['descripcion_producto'] }}. No implica fecha de reposición ni aceptación del cliente.</li>@endif
@endforeach</ul>
</section>
@endif
