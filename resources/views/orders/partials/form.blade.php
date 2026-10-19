@php
    $editando = isset($pedido);
    $filasPrevias = old('detalles', $editando
        ? $pedido->detalles->map(fn($d) => ['presentacion_producto_id' => $d->presentacion_producto_id, 'modalidad' => $d->modalidad, 'cantidad' => $d->cantidad])->all()
        : [['presentacion_producto_id'=>'', 'modalidad'=>'unidad','cantidad'=>1]]);
@endphp
<form method="POST" id="form-pedido" action="{{ $editando ? route('pedidos.update', $pedido) : route('pedidos.store') }}">
@csrf @if($editando) @method('PUT') @endif
<section class="form-section">
    <h2 class="form-section-title">Datos de la nota de pedido</h2>
    <div class="form-grid">
        <div class="form-group"><label for="cliente_id" class="form-label">Cliente *</label>
            <select class="form-control" id="cliente_id" name="cliente_id" required>
                <option value="">Selecciona un cliente activo</option>
                @foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected((string)old('cliente_id', $pedido->cliente_id ?? '') === (string)$cliente->id)>{{ $cliente->nombre }} — {{ $cliente->mercado ?: 'Sin mercado' }}</option>@endforeach
            </select>
            <span class="form-help">Los clientes inactivos no se muestran. <a style="color:var(--primary)" href="{{ route('clientes.index') }}">Gestionar clientes</a></span>
        </div>
        <div class="form-group"><label class="form-label">Vendedor responsable</label><div class="form-control" style="background:var(--surface-low)">{{ $editando ? ($pedido->vendedor?->first_name.' '.$pedido->vendedor?->last_name) : auth()->user()->first_name.' '.auth()->user()->last_name }}</div></div>
        <div class="form-group"><label class="form-label" for="fecha_pedido">Fecha de registro *</label>
            <input class="form-control" type="date" id="fecha_pedido" name="fecha_pedido" required min="{{ today('America/Lima')->subDays(30)->toDateString() }}" max="{{ today('America/Lima')->toDateString() }}" value="{{ old('fecha_pedido', $editando ? $pedido->fecha_pedido->format('Y-m-d') : now('America/Lima')->toDateString()) }}"></div>
        <div class="form-group"><label class="form-label" for="fecha_entrega">Fecha prevista de entrega *</label>
            <input class="form-control" type="date" id="fecha_entrega" name="fecha_entrega" required value="{{ old('fecha_entrega', $editando ? $pedido->fecha_entrega->format('Y-m-d') : now('America/Lima')->toDateString()) }}"></div>
        <div class="form-group"><label class="form-label" for="condicion_pago">Condición de pago *</label>
            <select class="form-control" name="condicion_pago" id="condicion_pago"><option value="contado" @selected(old('condicion_pago', $pedido->condicion_pago ?? 'contado') === 'contado')>Al contado</option>
                <option value="credito" @selected(old('condicion_pago', $pedido->condicion_pago ?? '') === 'credito')>Crédito</option></select></div>
        <div class="form-group"><label class="form-label" for="observaciones">Observaciones (opcional)</label>
            <textarea class="form-control" id="observaciones" name="observaciones" rows="2">{{ old('observaciones',$pedido->observaciones ?? '') }}</textarea></div>
    </div>
</section>
<section class="form-section">
    <div class="page-heading" style="margin-bottom:14px"><div><h2 class="form-section-title" style="margin-bottom:4px">Productos solicitados</h2>
        <div class="s2-note">El precio se obtiene del catálogo. Admite unidad o empaque si está habilitado. Máximo 18 líneas por nota física.</div></div>
        <button type="button" class="btn btn-secondary" id="agregar-fila"><span class="material-symbols-outlined">add</span> Agregar producto</button></div>
    @if(count($presentaciones) === 0)<div class="s2-error">No existen presentaciones activas. Crea presentaciones y registra precios en Catálogo de Productos antes de agregar un pedido.</div>@endif
    <div id="filas-pedido"></div>
    <template id="plantilla-fila">
        <div class="s2-product-row">
            <div><label>Producto / SKU *</label><select class="form-control js-presentacion" name="detalles[__INDEX__][presentacion_producto_id]" required></select></div>
            <div><label>Venta por *</label><select class="form-control js-modalidad" name="detalles[__INDEX__][modalidad]" required></select></div>
            <div><label>Cantidad *</label><input class="form-control js-cantidad" name="detalles[__INDEX__][cantidad]" type="number" min="1" max="10000" step="1" required value="1"></div>
            <div><label>Precio S/</label><input class="form-control js-precio" readonly placeholder="—" tabindex="-1"></div>
            <div><label>Importe S/</label><input class="form-control js-importe" readonly placeholder="0.00" tabindex="-1"></div>
            <button type="button" class="btn btn-danger remove-row js-eliminar" aria-label="Eliminar producto"><span class="material-symbols-outlined">delete</span></button>
        </div>
    </template>
    <div class="s2-resumen">Total del pedido: <span id="s2-total">S/ 0.00</span></div>
    <p class="s2-note">El servidor recalcula todos los importes al guardar; no depende de los valores mostrados en el navegador.</p>
</section>
<div class="form-actions"><a class="btn btn-secondary" href="{{ route('pedidos.index') }}">Cancelar</a><button class="btn btn-primary" type="submit" @disabled(count($presentaciones)===0)><span class="material-symbols-outlined">save</span> {{ $editando ? 'Guardar cambios' : 'Registrar pedido' }}</button></div>
</form>
@push('scripts')
<script>
window.configuracionSprint2 = {
    presentaciones: {{ Illuminate\Support\Js::from($presentaciones) }},
    filas: {{ Illuminate\Support\Js::from($filasPrevias) }}
};
</script>
<script src="{{ asset('js/sprint2-orders.js') }}"></script>
@endpush
