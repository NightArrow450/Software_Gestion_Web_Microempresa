@php($controlMateriales = (bool) config('envasado.control_materiales_exacto'))
<dialog id="envasado-integrado" class="cumplimiento-modal cumplimiento-no-imprimir" aria-labelledby="titulo-envasado-integrado"
        data-reabrir="{{ old('desde_lista') ? '1' : '0' }}">
    <div class="cumplimiento-encabezado">
        <h2 id="titulo-envasado-integrado">Registrar envasado real</h2>
        <button type="button" class="btn btn-secondary" data-cerrar-envasado aria-label="Cerrar registro de envasado">Cerrar</button>
    </div>
    <p>Presentación: <strong data-sku-envasado></strong>. Registra producción realmente realizada, sin reservarla para ningún cliente.</p>
    <p>@if($controlMateriales)Solo se utilizarán materiales configurados. Las mangas por empaque no se descuentan aquí.@else Solo se registran unidades terminadas; no se exige ni se descuenta inventario de materiales.@endif</p>
    <div role="alert" data-error-envasado>
        @if($errors->any() && old('desde_lista'))
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        @endif
    </div>
    <form method="POST" action="{{ route('envasado.previsualizar') }}" data-consultar-envasado>
        @csrf
        <input type="hidden" name="control_materiales_exacto" value="{{ $controlMateriales ? 1 : 0 }}">
        <input type="hidden" name="desde_lista" value="1">
        @if(isset($volverPedidoId))<input type="hidden" name="volver_pedido_id" value="{{ $volverPedidoId }}">@endif
        <input type="hidden" name="presentacion_producto_id" value="{{ old('presentacion_producto_id') }}">
        <input type="hidden" name="version_configuracion" value="">
        @foreach(['fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta, 'produccion_desde' => $produccionDesde, 'produccion_hasta' => $produccionHasta] as $campo => $valor)
            <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
        @endforeach
        <div class="cumplimiento-modal-grid">
            @if($controlMateriales)<label>Alternativa de materiales
                <select class="form-control" required name="configuracion_envasado_id">
                    <option value="">Selecciona una alternativa configurada</option>
                    @foreach($configuraciones as $configuracion)
                        <option value="{{ $configuracion->id }}" data-presentacion="{{ $configuracion->presentacion_producto_id }}" data-version="{{ $configuracion->version }}" @selected((int) old('configuracion_envasado_id') === $configuracion->id)>{{ $configuracion->nombre }}</option>
                    @endforeach
                </select>
            </label>
            @endif
            <label>Unidades realmente envasadas
                <input class="form-control" type="number" name="cantidad" min="1" max="1000000" step="1" required value="{{ old('cantidad') }}">
            </label>
            <label>Pedido opcional
                <select class="form-control" name="pedido_id">
                    <option value="">Producción general (sin pedido)</option>
                    @foreach($pedidos as $pedido)
                        <option value="{{ $pedido->id }}" data-presentaciones="{{ $pedido->detalles->pluck('presentacion_producto_id')->filter()->unique()->implode(',') }}" @selected((int) old('pedido_id') === $pedido->id)>Pedido {{ $pedido->numero }}</option>
                    @endforeach
                </select>
            </label>
            <label>Observaciones
                <textarea class="form-control" name="observaciones" maxlength="1000">{{ old('observaciones') }}</textarea>
            </label>
        </div>
        <p>@if($controlMateriales)Este modo requiere materiales configurados y existencias verificadas. @endif El vínculo opcional con un pedido no cambia su estado ni reserva stock.</p>
        <button type="submit" class="btn btn-primary">{{ $controlMateriales ? 'Consultar consumos antes de confirmar' : 'Revisar unidades antes de confirmar' }}</button>
    </form>
    <div data-vista-previa-envasado aria-live="polite"></div>
</dialog>
