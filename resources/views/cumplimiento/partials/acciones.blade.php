@php
    $puedePreparar = auth()->user()->role?->tienePermiso('cumplimiento.preparar') ?? false;
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
            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Marcar pedido como listo para despacho?')">Finalizar preparación</button>
        </form>
    @else
        <p class="muted">No hay acciones de preparación disponibles para este estado o tu rol.</p>
    @endif
</div>
