@php
    $puedePreparar = auth()->user()->role?->tienePermiso('cumplimiento.preparar') ?? false;
    $puedeDespachar = auth()->user()->role?->tienePermiso('cumplimiento.despachar') ?? false;
    $puedeEntregar = auth()->user()->role?->tienePermiso('cumplimiento.entregar') ?? false;
@endphp
<div class="cumplimiento-acciones">
    @if($puedePreparar && $pedido->estado === 'confirmado')
        <form method="POST" action="{{ route('cumplimiento.iniciar-preparacion', $pedido) }}">
            @csrf
            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Iniciar preparación del pedido?')">Iniciar preparación</button>
        </form>
    @elseif($puedePreparar && $pedido->estado === 'en_preparacion')
        <form method="POST" action="{{ route('cumplimiento.finalizar-preparacion', $pedido) }}">
            @csrf
            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Marcar como listo para despacho?')">Finalizar preparación</button>
        </form>
    @elseif($puedeDespachar && $pedido->estado === 'listo_despacho')
        <form method="POST" action="{{ route('cumplimiento.despachar', $pedido) }}">
            @csrf
            <label for="observacion_despacho">Observación de despacho (opcional)</label>
            <textarea id="observacion_despacho" name="observacion_despacho" maxlength="1000" class="form-control" rows="3">{{ old('observacion_despacho') }}</textarea>
            <p class="muted">Al despachar se descontarán automáticamente las unidades terminadas del inventario.
               Si falta stock, el despacho será rechazado sin descontar nada. Registra primero las
               unidades envasadas mediante una entrada de inventario.</p>
            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Despachar este pedido y descontar las unidades del inventario?')">Registrar despacho</button>
        </form>
    @elseif($puedeEntregar && $pedido->estado === 'despachado')
        <form method="POST" action="{{ route('cumplimiento.entregar', $pedido) }}">
            @csrf
            <label for="observacion_entrega">Observación de entrega (opcional)</label>
            <textarea id="observacion_entrega" name="observacion_entrega" maxlength="1000" class="form-control" rows="3">{{ old('observacion_entrega') }}</textarea>
            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Confirmar que el cliente recibió el pedido?')">Confirmar entrega</button>
        </form>
    @elseif($pedido->estado === 'entregado')
        <p class="cumplimiento-nota">Pedido entregado. No se puede registrar la entrega dos veces.</p>
    @else
        <p class="muted">No hay acciones disponibles para este estado o tu rol.</p>
    @endif
</div>
