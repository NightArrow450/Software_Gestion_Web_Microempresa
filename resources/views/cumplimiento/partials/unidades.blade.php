<section class="cumplimiento-tarjeta">
<h2>Preparación y atención por unidades</h2>
<p>El stock no demuestra que exista líquido preparado. Comprueba físicamente la disponibilidad. Registrar preparación no produce unidades, no consume materiales y no reserva stock; al despachar se vuelve a bloquear y comprobar el inventario.</p>
<p>Las cantidades en espera siguen pendientes. Para reducir, sustituir, dividir aromas o retirar definitivamente, Operaciones debe editar el pedido con un motivo.</p>
@foreach($pedido->detalles as $detalle)
@php
    $salidas = (int) ($atencionUnidades['despachadas'][$detalle->id] ?? 0);
    $pendiente = max(0, $detalle->cantidad - $salidas);
    $preparacion = $atencionUnidades['preparadas']->get($detalle->id);
    $preparadas = max(0, (int)($preparacion->cantidad ?? 0) - ($salidas - (int)($preparacion->despachadas_base ?? $salidas)));
    $disponible = (int)($atencionUnidades['stock'][$detalle->presentacion_producto_id] ?? 0);
@endphp
<article class="cumplimiento-tarjeta"><h3>{{ $detalle->descripcion_producto }} · {{ $detalle->codigo_sku }}</h3>
<p>Solicitadas vigentes: <strong>{{ $detalle->cantidad }}</strong> · Despachadas: {{ $salidas }} · Pendientes: {{ $pendiente }} · En espera: {{ $detalle->cantidad_espera }}</p>
<p>Stock terminado: {{ $disponible }} · Faltan frente al stock: {{ max(0, $pendiente - $disponible) }} · Envasado neto vinculado: {{ $atencionUnidades['envasadas'][$detalle->presentacion_producto_id] ?? 0 }} (incluido en stock) · Preparadas por despachar: {{ min($preparadas, $pendiente) }}</p>
@if($preparacion?->incidencia)<p><strong>Incidencia de disponibilidad:</strong> {{ $preparacion->incidencia }} · {{ $preparacion->motivo }}</p>@endif
@if(auth()->user()->role?->tienePermiso('cumplimiento.preparar') && in_array($pedido->estado, ['confirmado','en_preparacion','listo_despacho','despacho_parcial'], true))
<form method="POST" action="{{ route('pedidos.unidades.preparar', $pedido) }}">@csrf @include('components.token-operacion')
<input type="hidden" name="version_pedido" value="{{ $pedido->version }}"><input type="hidden" name="pedido_detalle_id" value="{{ $detalle->id }}">
<div class="form-grid">
<label>Unidades físicamente preparadas por despachar<input class="form-control" type="number" name="cantidad" min="0" step="1" required value="{{ min($preparadas, $pendiente) }}"></label>
<label>Incidencia de disponibilidad<select class="form-control" name="incidencia"><option value="">Sin incidencia nueva</option><option value="liquido">Falta líquido preparado</option><option value="envases">Faltan envases</option><option value="etiquetas_tapas">Faltan etiquetas o tapas</option><option value="insumos_quimicos">Faltan insumos químicos</option><option value="tiempo">Falta tiempo para preparar</option><option value="otro">Otra incidencia</option></select></label>
<label>Motivo y comprobación física<textarea class="form-control" name="motivo" minlength="10" maxlength="1000" required></textarea></label>
</div><button class="btn btn-primary">Registrar preparación real o incidencia</button></form>
@endif
@if(auth()->user()->role?->tienePermiso('envasado.registrar') && auth()->user()->role?->tienePermiso('envasado.ver') && $pendiente > 0)
<button type="button" class="btn btn-primary" data-registrar-envasado="{{ $detalle->presentacion_producto_id }}" data-sku="{{ $detalle->codigo_sku }}" aria-haspopup="dialog" aria-controls="envasado-integrado">Confirmar envasado físico desde preparación</button>
@endif
</article>
@endforeach
@if(auth()->user()->role?->tienePermiso('cumplimiento.despachar') && in_array($pedido->estado, ['en_preparacion','listo_despacho','despacho_parcial'], true))
<form method="POST" action="{{ route('pedidos.unidades.despachar', $pedido) }}" data-despacho-unidades>@csrf @include('components.token-operacion')
<h3>Registrar esta salida física</h3><input type="hidden" name="version_pedido" value="{{ $pedido->version }}"><input type="hidden" name="operacion" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
<p>Selecciona los paquetes completos según su composición declarada y añade las unidades sueltas que salen. Un paquete puede estar incompleto respecto de su capacidad; no se exige llenarlo. Para abrir o redistribuir un paquete, edita antes la agrupación con un motivo.</p>
@foreach(($atencionUnidades['foto']['agrupaciones'] ?? []) as $grupoSalida)
@if(!isset($atencionUnidades['distribucion']['paquetes'][$grupoSalida['identidad']]))
<label class="unidades-grupo"><input type="checkbox" name="paquetes[]" value="{{ $grupoSalida['identidad'] }}" data-paquete-salida data-componentes="{{ json_encode($grupoSalida['componentes']) }}"> Paquete {{ $loop->iteration }}: {{ $grupoSalida['unidades'] }} de {{ $grupoSalida['capacidad'] }} unidades</label>
<ul>@foreach($grupoSalida['componentes'] as $componenteSalida)<li>{{ $componenteSalida['cantidad'] }} unidades · {{ $pedido->detalles->firstWhere('presentacion_producto_id', $componenteSalida['sku_id'])?->descripcion_producto }}</li>@endforeach</ul>
@else<p>Paquete {{ $loop->iteration }}: ya despachado; se conserva su fotografía.</p>@endif
@endforeach
@foreach($pedido->detalles as $i=>$detalle)
<input type="hidden" name="detalles[{{ $i }}][id]" value="{{ $detalle->id }}">
<input type="hidden" name="detalles[{{ $i }}][cantidad]" value="0" data-cantidad-salida data-sku="{{ $detalle->presentacion_producto_id }}">
<label>{{ $detalle->descripcion_producto }} — Unidades sueltas que salen ahora<input class="form-control" data-salida-suelta data-sku="{{ $detalle->presentacion_producto_id }}" type="number" min="0" step="1" required value="0"></label>
<p>Total de este SKU en esta salida (paquetes más sueltas): <output data-total-salida data-sku="{{ $detalle->presentacion_producto_id }}">0</output></p>
@endforeach
<p role="alert" data-error-distribucion></p><noscript>Activa JavaScript para sumar la distribución de esta salida. El servidor comprueba de nuevo todas las cantidades.</noscript>
<label>Motivo e instrucciones para reparto<textarea class="form-control" name="motivo" required minlength="10" maxlength="1000"></textarea></label>
<button class="btn btn-primary">Confirmar despacho de estas unidades</button><p>Las otras unidades conservan su obligación o espera. No se descuenta dos veces una operación repetida.</p>
</form>
@endif
@if($atencionUnidades['salidas']->isNotEmpty())
<h3>Salidas físicas registradas</h3>
@foreach($atencionUnidades['salidas'] as $salidaReal)
@php
    $distribucionReal = json_decode($salidaReal->distribucion, true, 512, JSON_THROW_ON_ERROR);
@endphp
<details><summary>Despacho {{ $salidaReal->id }} · {{ $salidaReal->created_at }} · versión comercial {{ $salidaReal->version_comercial }}</summary><p>{{ $salidaReal->motivo }}</p>
@foreach($distribucionReal['paquetes'] as $paqueteReal)<p>Paquete {{ $loop->iteration }} de esta salida: {{ $paqueteReal['unidades'] }} de {{ $paqueteReal['capacidad'] }} unidades · referencia {{ $paqueteReal['identidad'] }}</p><ul>@foreach($paqueteReal['componentes'] as $cReal)<li>SKU {{ $cReal['sku_id'] }}: {{ $cReal['cantidad'] }} unidades</li>@endforeach</ul>@endforeach
<ul>@foreach($distribucionReal['sueltas'] as $skuReal=>$cantidadReal)<li>Sueltas · SKU {{ $skuReal }}: {{ $cantidadReal }} unidades</li>@endforeach</ul></details>
@endforeach
@endif
</section>
@push('styles')<link rel="stylesheet" href="{{ asset('css/pedidos-unidades.css') }}">@endpush
@push('scripts')<script src="{{ asset('js/despacho-unidades.js') }}" defer></script>@endpush
@if(auth()->user()->role?->tienePermiso('envasado.registrar') && auth()->user()->role?->tienePermiso('envasado.ver'))
@include('cumplimiento.partials.envasado-integrado', ['configuraciones'=>$atencionUnidades['configuraciones'], 'pedidos'=>collect([$pedido]), 'fechaDesde'=>null, 'fechaHasta'=>null, 'produccionDesde'=>today('America/Lima')->toDateString(), 'produccionHasta'=>today('America/Lima')->toDateString(), 'volverPedidoId'=>$pedido->id])
@endif
