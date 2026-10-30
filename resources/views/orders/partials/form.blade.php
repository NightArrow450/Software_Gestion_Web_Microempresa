@php
    $editando = isset($pedido);
    $filasPrevias = old('detalles', $editando
        ? $pedido->detalles->where('tipo_linea', 'normal')->map(fn($d) => ['id' => $d->id, 'tipo_linea' => $d->tipo_linea ?? 'normal', 'precio_unitario' => $d->precio_unitario, 'importe' => $d->importe, 'presentacion_producto_id' => $d->presentacion_producto_id, 'modalidad' => $d->modalidad, 'cantidad' => $d->cantidad, 'unidades_por_empaque' => $d->unidades_por_empaque ?? $d->presentacion?->unidades_por_empaque])->all()
        : [['presentacion_producto_id'=>'', 'modalidad'=>'unidad','cantidad'=>1]]);
    $filasPrevias = array_values(array_filter($filasPrevias, fn($fila) => is_array($fila) && ($fila['tipo_linea'] ?? 'normal') === 'normal'));
    foreach ($filasPrevias as &$filaHistoricaPrevia) {
        $fuenteHistorica = $pedido->detalles->firstWhere('id', (int) ($filaHistoricaPrevia['id'] ?? 0));
        if ($fuenteHistorica) {
            $filaHistoricaPrevia['precio_unitario'] = $fuenteHistorica->precio_unitario;
            $filaHistoricaPrevia['importe'] = $fuenteHistorica->importe;
        }
    }
    unset($filaHistoricaPrevia);
@endphp
<form method="POST" id="form-pedido" action="{{ $editando ? route('pedidos.update', $pedido) : route('pedidos.store') }}">
@csrf @include('components.token-operacion')
@if(isset($pedido))
<input type="hidden" name="_version" value="{{ $pedido->version }}">
<input type="hidden" name="_updated_at" value="{{ $pedido->getRawOriginal('updated_at') }}">
@endif @if($editando) @method('PUT') @endif
@foreach($pedido->detalles->where('tipo_linea', '!=', 'normal') as $anteriorSinSku)
<div class="form-section" role="note"><strong>{{ $anteriorSinSku->descripcion_producto }}</strong><p>Referencia anterior sin SKU físico: se conserva sin convertir precios ni habilitar su modalidad retirada.</p>
@foreach(['id','tipo_linea','presentacion_producto_id','modalidad','cantidad','unidades_por_empaque'] as $campo)
<input type="hidden" name="detalles[anterior_{{ $anteriorSinSku->id }}][{{ $campo }}]" value="{{ $anteriorSinSku->$campo }}">
@endforeach</div>
@endforeach
<section class="form-section">
    <h2>Ajuste de pedido histórico</h2>
    <p>Se conservan la modalidad, el tamaño y el precio pactados. El importe revisado es una decisión comercial explícita; no modifica pagos ni movimientos anteriores. Las unidades físicas revisadas solo se admiten antes del despacho y junto con un importe revisado.</p>
    <label for="motivo-historico">Motivo del ajuste *</label><textarea id="motivo-historico" name="motivo" class="form-control" required minlength="10" maxlength="1000">{{ old('motivo') }}</textarea>
    @foreach($pedido->detalles->where('tipo_linea', 'normal') as $historica)
    <details><summary>Ajuste explícito: {{ $historica->descripcion_producto }} · {{ $historica->descripcionModalidad() }} · importe vigente S/ {{ $historica->importe }}</summary>
        <label for="importe-historico-{{ $historica->id }}">Importe total revisado de esta línea (opcional)</label><input class="form-control" id="importe-historico-{{ $historica->id }}" name="ajustes_historicos[{{ $historica->id }}][importe_revisado]" type="number" min="0" max="999999.99" step="0.01" value="{{ old('ajustes_historicos.'.$historica->id.'.importe_revisado') }}">
        @if($historica->presentacion_producto_id !== null)<label for="fisica-historica-{{ $historica->id }}">Unidades físicas revisadas (opcional; no convierte el precio)</label><input class="form-control" id="fisica-historica-{{ $historica->id }}" name="ajustes_historicos[{{ $historica->id }}][cantidad_fisica_revisada]" type="number" min="1" step="1" value="{{ old('ajustes_historicos.'.$historica->id.'.cantidad_fisica_revisada') }}">@endif
    </details>
    @endforeach
</section>
<section class="form-section">
    <h2 class="form-section-title">Datos originales de la nota de pedido</h2>
    <p>La cabecera histórica se conserva. Los ajustes de cantidades, importes e incidencias se registran abajo con un motivo.</p>
    <div class="form-grid">
        <div class="form-group s2-cliente-busqueda"><label for="buscar-cliente" class="form-label">Cliente *</label>
            <input class="form-control" type="search" id="buscar-cliente" placeholder="Escribe el nombre, documento o mercado" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="resultados-clientes" hidden>
            <div class="s2-sugerencias" id="resultados-clientes" role="listbox" hidden></div>
            <select class="form-control" id="cliente_id" name="cliente_id" required disabled>
                <option value="">Selecciona un cliente activo</option>
                @foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected((string)old('cliente_id', $pedido->cliente_id ?? '') === (string)$cliente->id)>{{ $cliente->nombre }} — {{ $cliente->mercado ?: 'Sin mercado' }}</option>@endforeach
            </select>
            <span class="form-help">Los clientes inactivos no se muestran. <a style="color:var(--primary)" href="{{ route('clientes.index') }}">Gestionar clientes</a></span>
        </div>
        <div class="form-group"><label class="form-label">Vendedor responsable</label><div class="form-control" style="background:var(--surface-low)">{{ $editando ? ($pedido->vendedor?->first_name.' '.$pedido->vendedor?->last_name) : auth()->user()->first_name.' '.auth()->user()->last_name }}</div></div>
        <div class="form-group"><label class="form-label" for="fecha_pedido">Fecha de registro *</label>
            <input class="form-control" type="date" id="fecha_pedido" name="fecha_pedido" required readonly min="{{ today('America/Lima')->subDays(30)->toDateString() }}" max="{{ today('America/Lima')->toDateString() }}" value="{{ old('fecha_pedido', $editando ? $pedido->fecha_pedido->format('Y-m-d') : now('America/Lima')->toDateString()) }}"></div>
        <div class="form-group"><label class="form-label" for="fecha_entrega">Fecha prevista de entrega *</label>
            <input class="form-control" type="date" id="fecha_entrega" name="fecha_entrega" required readonly value="{{ old('fecha_entrega', $editando ? $pedido->fecha_entrega->format('Y-m-d') : now('America/Lima')->toDateString()) }}"></div>
        <div class="form-group"><label class="form-label" for="condicion_pago">Condición de pago *</label>
            <select class="form-control" name="condicion_pago" id="condicion_pago" disabled><option value="contado" @selected(old('condicion_pago', $pedido->condicion_pago ?? 'contado') === 'contado')>Al contado</option>
                <option value="credito" @selected(old('condicion_pago', $pedido->condicion_pago ?? '') === 'credito')>Crédito</option></select></div>
        <div class="form-group"><label class="form-label" for="observaciones">Observaciones (opcional)</label>
            <textarea class="form-control" id="observaciones" name="observaciones" rows="2">{{ old('observaciones',$pedido->observaciones ?? '') }}</textarea></div>
    </div>
</section>
<section class="form-section">
    <div class="page-heading" style="margin-bottom:14px"><div><h2 class="form-section-title" style="margin-bottom:4px">Productos solicitados</h2>
        <div class="s2-note">En líneas existentes se conserva el precio histórico. No cambies su modalidad ni tamaño. Las adiciones se venden por unidades. El servidor valida los ajustes explícitos.</div></div>
        <button type="button" class="btn btn-secondary" id="agregar-fila"><span class="material-symbols-outlined">search</span> Buscar producto</button></div>
    @if(count($presentaciones) === 0)<div class="s2-error">No existen presentaciones activas. Crea presentaciones y registra precios en Catálogo de Productos antes de agregar un pedido.</div>@endif
    <div class="form-group s2-busqueda" id="busqueda-productos">
        <label class="form-label" for="buscar-presentacion">Buscar producto por nombre o SKU</label>
        <input class="form-control" type="search" id="buscar-presentacion" placeholder="Ej.: Quita, lejía o código SKU" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="resultados-presentaciones">
        <div class="s2-sugerencias" id="resultados-presentaciones" role="listbox" hidden></div>
        <p class="s2-note" id="aviso-buscador" aria-live="polite">Escribe para buscar. Selecciona la presentación y modalidad de venta.</p>
    </div>

    <div id="filas-pedido"></div>
    <template id="plantilla-fila">
        <div class="s2-product-row">
            <div><input type="hidden" class="js-detalle-id" name="detalles[__INDEX__][id]"><label>Producto / SKU *</label><input type="hidden" class="js-presentacion" name="detalles[__INDEX__][presentacion_producto_id]"><div class="form-control s2-nombre-producto js-nombre-producto"></div></div>
            <div><label>Venta por *</label><select class="form-control js-modalidad" name="detalles[__INDEX__][modalidad]" required></select>
                <div class="s2-tamano-empaque js-campo-empaque" hidden>
                    <label class="js-label-empaque">Unidades por empaque *</label>
                    <input class="form-control js-tamano-empaque" name="detalles[__INDEX__][unidades_por_empaque]" type="number" min="2" max="1000" step="1" inputmode="numeric" required disabled>
                    <span class="form-help js-descripcion-empaque" aria-live="polite"></span>
                </div>
            </div>
            <div><label>Cantidad *</label><input class="form-control js-cantidad" name="detalles[__INDEX__][cantidad]" type="number" min="0" max="4294967295" step="1" required value="1"></div>
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
    clientes: {{ Illuminate\Support\Js::from($clientes->map(fn($cliente) => [
        'id' => $cliente->id,
        'nombre' => $cliente->nombre,
        'documento' => $cliente->documento,
        'mercado' => $cliente->mercado,
    ])->values()->all()) }},
    filas: {{ Illuminate\Support\Js::from($filasPrevias) }},
    historico: true,
    ajusteCabeceraCentimos: {{ (int) round(((float) $pedido->total - (float) $pedido->detalles->sum('importe')) * 100) }}
};
</script>
<script src="{{ asset('js/pedidos-busqueda-utils.js') }}"></script>
<script src="{{ asset('js/sprint2-orders.js') }}"></script>
@endpush

