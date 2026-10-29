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
<form method="POST" action="{{ route('pedidos.unidades.despachar', $pedido) }}">@csrf @include('components.token-operacion')
<h3>Registrar esta salida física</h3><input type="hidden" name="version_pedido" value="{{ $pedido->version }}"><input type="hidden" name="operacion" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
@foreach($pedido->detalles as $i=>$detalle)
<input type="hidden" name="detalles[{{ $i }}][id]" value="{{ $detalle->id }}">
<label>{{ $detalle->descripcion_producto }} — Unidades que salen ahora<input class="form-control" name="detalles[{{ $i }}][cantidad]" type="number" min="0" step="1" required value="0"></label>
@endforeach
<label>Motivo e instrucciones para reparto<textarea class="form-control" name="motivo" required minlength="10" maxlength="1000"></textarea></label>
<button class="btn btn-primary">Confirmar despacho de estas unidades</button><p>Las otras unidades conservan su obligación o espera. No se descuenta dos veces una operación repetida.</p>
</form>
@endif
</section>
@if(auth()->user()->role?->tienePermiso('envasado.registrar') && auth()->user()->role?->tienePermiso('envasado.ver'))
@include('cumplimiento.partials.envasado-integrado', ['configuraciones'=>$atencionUnidades['configuraciones'], 'pedidos'=>collect([$pedido]), 'fechaDesde'=>null, 'fechaHasta'=>null, 'produccionDesde'=>today('America/Lima')->toDateString(), 'produccionHasta'=>today('America/Lima')->toDateString(), 'volverPedidoId'=>$pedido->id])
@endif
