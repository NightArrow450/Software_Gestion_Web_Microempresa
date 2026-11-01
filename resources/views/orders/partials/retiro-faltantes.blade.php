@if(!empty($resumenRetiro))
<section class="form-section retiro-faltantes" aria-label="Decisión comercial sobre faltantes">
    <h2>Productos faltantes que el cliente ya no desea</h2>
    <p>La espera temporal mantiene el compromiso. El retiro definitivo reduce únicamente lo no despachado. Un rechazo físico se registra en Entregas, solo para unidades que sí salieron.</p>
    <details><summary>Retirar productos faltantes</summary>
        <form method="POST" action="{{ route('pedidos.retirar-faltantes', $pedido) }}" data-retiro-faltantes data-total-centimos="{{ (int) round((float) $pedido->total * 100) }}">
            @csrf @include('components.token-operacion')
            <input type="hidden" name="version" value="{{ old('version', $pedido->version) }}">
            <div class="table-container"><table class="app-table"><thead><tr><th>Producto / SKU</th><th>Vigentes</th><th>Despachadas</th><th>Pendientes / en espera</th><th>Retirar definitivamente</th></tr></thead><tbody>
            @foreach($resumenRetiro as $linea)
                <tr><td>{{ $linea['nombre'] }} · {{ $linea['sku'] }}</td><td>{{ $linea['vigentes'] }}</td><td>{{ $linea['despachadas'] }}</td><td>{{ $linea['pendientes'] }} / {{ $linea['espera'] }}</td>
                    <td><label class="sr-only" for="retirar-{{ $linea['id'] }}">Retirar {{ $linea['sku'] }}</label><input class="form-control" type="number" id="retirar-{{ $linea['id'] }}" name="retirar[{{ $linea['id'] }}]" min="0" max="{{ $linea['pendientes'] }}" step="1" value="{{ old('retirar.'.$linea['id'], 0) }}" data-precio-centimos="{{ $linea['precio_centimos'] }}" required></td></tr>
            @endforeach
            </tbody></table></div>
            <p data-resumen-retiro role="status" aria-live="polite">El servidor conservará los precios pactados y calculará el nuevo compromiso comercial. No se modifican pagos ni comprobantes.</p>
            <label>Motivo informado por el cliente<textarea class="form-control" name="motivo" minlength="10" maxlength="1000" required>{{ old('motivo') }}</textarea></label>
            <label class="opcion-confirmacion"><input type="checkbox" name="resolver_agrupaciones" value="1" @checked(old('resolver_agrupaciones'))> Si corresponde, desarmar las agrupaciones aún no despachadas de los SKU retirados; las unidades restantes se entregarán sueltas. Los paquetes despachados se conservan.</label>
            <p>Si prefieres redistribuir los paquetes pendientes, utiliza <a href="{{ route('pedidos.edit', $pedido) }}">Editar pedido</a> antes de confirmar el retiro.</p>
            <label class="opcion-confirmacion"><input type="checkbox" required> Confirmo el retiro comercial definitivo; estas unidades no fueron despachadas ni rechazadas físicamente.</label>
            <div class="acciones-formulario"><button class="btn btn-danger" type="submit">Confirmar retiro de faltantes</button></div>
        </form>
    </details>
</section>
@push('scripts')<script src="{{ asset('js/retiro-faltantes.js') }}" defer></script>@endpush
@endif
