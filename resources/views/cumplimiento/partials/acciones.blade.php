@php
    $puedePreparar = auth()->user()->role?->tienePermiso('cumplimiento.preparar') ?? false;
    $puedeDespachar = auth()->user()->role?->tienePermiso('cumplimiento.despachar') ?? false;
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
            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Registrar salida del pedido?')">Registrar despacho</button>
        </form>
    @else
        <p class="muted">No hay acciones disponibles para este estado o tu rol.</p>
    @endif
</div>
