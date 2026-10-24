@php
    $rolEntrega = auth()->user()->role;
    $cerradaEntrega = $resumenEntrega['incidencia']?->resuelto_en !== null;
    $puedeResultado = !$cerradaEntrega && !$resumenEntrega['historico_sin_desglose'] && in_array($pedido->estado, ['despachado', 'entrega_pendiente'], true) && $rolEntrega?->tienePermiso('cumplimiento.entregar');
@endphp
<section class="entrega-panel" aria-label="Registrar entrega o retorno">
    @if($puedeResultado)
        <details><summary>Registrar cantidades aceptadas, rechazadas o pendientes</summary>
            <form method="POST" action="{{ route('cumplimiento.resultado-entrega', $pedido) }}" class="entrega-formulario">
                @csrf @include('components.token-operacion')
                <input type="hidden" name="version" value="{{ $resumenEntrega['version'] }}">
                <p>Indica únicamente las cantidades nuevas de esta visita, en unidades físicas. Lo que no se resuelva seguirá pendiente.</p>
                <label>Fecha y hora del hecho<input type="datetime-local" name="ocurrido_en" value="{{ now('America/Lima')->format('Y-m-d\TH:i') }}" required></label>
                @foreach($resumenEntrega['lineas'] as $linea)
                    <fieldset><legend>{{ $linea['sku'] }} · {{ $linea['descripcion'] }} · pendientes {{ $linea['pendientes'] ?? 'No comprobado' }}</legend>
                        <input type="hidden" name="detalles[{{ $loop->index }}][pedido_detalle_id]" value="{{ $linea['id'] }}">@if(isset($linea['componente_id']))<input type="hidden" name="detalles[{{ $loop->index }}][despacho_componente_id]" value="{{ $linea['componente_id'] }}">@endif
                        <div class="entrega-grid"><label>Nuevas unidades aceptadas<input type="number" name="detalles[{{ $loop->index }}][aceptadas]" min="0" max="{{ $linea['pendientes'] ?? 0 }}" step="1" value="0" required></label>
                        <label>Nuevas unidades rechazadas<input type="number" name="detalles[{{ $loop->index }}][rechazadas]" min="0" max="{{ $linea['pendientes'] ?? 0 }}" step="1" value="0" required></label></div>
                        <label>Motivo informado del rechazo (obligatorio si hay rechazo)<textarea name="detalles[{{ $loop->index }}][motivo]" minlength="10" maxlength="1000"></textarea></label>
                    </fieldset>
                @endforeach
                <label>Observaciones<textarea name="observaciones" maxlength="1000" placeholder="Si todo sigue pendiente, explica el motivo (mínimo 10 caracteres)."></textarea></label>
                <button type="submit" class="btn btn-primary">Registrar resultado sin modificar stock</button>
            </form>
        </details>
    @endif
    @if(!$cerradaEntrega && $rolEntrega?->tienePermiso('cumplimiento.retornos.registrar') && array_sum(array_column($resumenEntrega['lineas'], 'por_retornar')) > 0)
        <details><summary>Registrar retorno físico o no recuperación</summary>
            <form method="POST" action="{{ route('cumplimiento.retornos', $pedido) }}" class="entrega-formulario">
                @csrf @include('components.token-operacion')
                <input type="hidden" name="version" value="{{ $resumenEntrega['version'] }}">
                <p>Las unidades aptas quedarán pendientes de aprobación. Las dañadas, en evaluación y no recuperadas no aumentan el stock.</p>
                <label>Fecha y hora de recepción o constatación<input type="datetime-local" name="ocurrido_en" value="{{ now('America/Lima')->format('Y-m-d\TH:i') }}" required></label>
                @foreach($resumenEntrega['lineas'] as $linea)
                    <fieldset><legend>{{ $linea['sku'] }} · {{ $linea['descripcion'] }} · rechazadas pendientes de retorno {{ $linea['por_retornar'] }}</legend>
                        <input type="hidden" name="detalles[{{ $loop->index }}][pedido_detalle_id]" value="{{ $linea['id'] }}">@if(isset($linea['componente_id']))<input type="hidden" name="detalles[{{ $loop->index }}][despacho_componente_id]" value="{{ $linea['componente_id'] }}">@endif
                        <div class="entrega-grid">
                            @foreach(['aptas' => 'Recibidas aptas', 'danadas' => 'Recibidas dañadas', 'en_revision' => 'Recibidas en evaluación', 'no_recuperadas' => 'Perdidas o no recuperadas (sin recepción física)'] as $campo => $etiqueta)
                                <label>{{ $etiqueta }}<input type="number" name="detalles[{{ $loop->parent->index }}][{{ $campo }}]" min="0" max="{{ $linea['por_retornar'] }}" step="1" value="0" required></label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                <label>Observaciones y evidencia del conteo<textarea name="observaciones" minlength="10" maxlength="1000" required></textarea></label>
                <button type="submit" class="btn btn-primary">Registrar recepción sin reincorporar existencias</button>
            </form>
        </details>
    @endif
    @if(!$cerradaEntrega)
        @foreach($resumenEntrega['retornos'] as $retorno)
            @php($aptasPendientes = $retorno->aptas + $retorno->revisadas_aptas - $retorno->reincorporadas)
            @if($aptasPendientes > 0 && $rolEntrega?->tienePermiso('cumplimiento.retornos.aprobar'))
                <details><summary>Aprobar reincorporación · recepción {{ $retorno->retorno_id }} · {{ $resumenEntrega['lineas'][\App\Services\ControlEntregaPedido::claveFisica($retorno->pedido_detalle_id, $retorno->despacho_componente_id ?? null)]['sku'] }} · aptas pendientes {{ $aptasPendientes }}</summary>
                    <form method="POST" action="{{ route('cumplimiento.aprobar-retorno', $pedido) }}" class="entrega-formulario">
                        @csrf @include('components.token-operacion')
                        <input type="hidden" name="version" value="{{ $resumenEntrega['version'] }}"><input type="hidden" name="retorno_detalle_id" value="{{ $retorno->id }}">
                        <label>Unidades físicamente aptas a reincorporar<input type="number" name="cantidad" min="1" max="{{ $aptasPendientes }}" step="1" required></label>
                        <label>Motivo de aprobación y comprobación física<textarea name="motivo" minlength="10" maxlength="1000" required></textarea></label>
                        <button type="submit" class="btn btn-primary">Autorizar entrada de estas unidades al inventario</button>
                    </form>
                </details>
            @endif
            @php($porClasificar = $retorno->en_revision - $retorno->revisadas_aptas - $retorno->revisadas_danadas)
            @if($porClasificar > 0 && $rolEntrega?->tienePermiso('cumplimiento.incidencias.revisar'))
                <details><summary>Evaluar recepción {{ $retorno->retorno_id }} · {{ $resumenEntrega['lineas'][\App\Services\ControlEntregaPedido::claveFisica($retorno->pedido_detalle_id, $retorno->despacho_componente_id ?? null)]['sku'] }} · por clasificar {{ $porClasificar }}</summary>
                    <form method="POST" action="{{ route('cumplimiento.incidencia.clasificar', $pedido) }}" class="entrega-formulario">
                        @csrf @include('components.token-operacion')
                        <input type="hidden" name="version" value="{{ $resumenEntrega['version'] }}"><input type="hidden" name="retorno_detalle_id" value="{{ $retorno->id }}">
                        <div class="entrega-grid"><label>Aptas verificadas<input type="number" name="aptas" value="0" min="0" max="{{ $porClasificar }}" step="1" required></label><label>Dañadas verificadas<input type="number" name="danadas" value="0" min="0" max="{{ $porClasificar }}" step="1" required></label></div>
                        <label>Motivo y resultado de la evaluación<textarea name="motivo" minlength="10" maxlength="1000" required></textarea></label>
                        <button type="submit" class="btn btn-secondary">Clasificar sin modificar stock</button>
                    </form>
                </details>
            @endif
        @endforeach
    @endif
    @foreach(['revisar' => ['cumplimiento.incidencias.revisar', 'Registrar revisión comercial'], 'resolver' => ['cumplimiento.incidencias.resolver', 'Cerrar expediente con resolución comercial'], 'reabrir' => ['cumplimiento.incidencias.corregir', 'Reabrir expediente para rectificación']] as $accion => [$permiso, $etiqueta])
        @if($rolEntrega?->tienePermiso($permiso) && ($accion === 'reabrir' ? $cerradaEntrega : !$cerradaEntrega))
            <details><summary>{{ $etiqueta }}</summary><form method="POST" action="{{ route('cumplimiento.incidencia.'.$accion, $pedido) }}" class="entrega-formulario">
                @csrf @include('components.token-operacion')<input type="hidden" name="version" value="{{ $resumenEntrega['version'] }}">
                <label>Motivo y observaciones<textarea name="motivo" minlength="10" maxlength="1000" required></textarea></label>
                <p>La resolución no modifica pagos, comprobantes ni el estado histórico de aceptación o rechazo.</p><button type="submit" class="btn btn-secondary">{{ $etiqueta }}</button>
            </form></details>
        @endif
    @endforeach
    @if(!$cerradaEntrega && !$resumenEntrega['historico_sin_desglose'] && $rolEntrega?->tienePermiso('cumplimiento.incidencias.corregir'))
        <details><summary>Rectificación administrativa autorizada de cantidades</summary>
            <form method="POST" action="{{ route('cumplimiento.rectificar-entrega', $pedido) }}" class="entrega-formulario">
                @csrf @include('components.token-operacion')<input type="hidden" name="version" value="{{ $resumenEntrega['version'] }}">
                <p>Indica diferencias, no totales: un valor negativo resta y uno positivo suma. El historial original se conserva. No se permiten rechazos menores que los retornos ya registrados.</p>
                @foreach($resumenEntrega['lineas'] as $linea)
                    <fieldset><legend>{{ $linea['sku'] }} · {{ $linea['descripcion'] }}</legend><input type="hidden" name="detalles[{{ $loop->index }}][pedido_detalle_id]" value="{{ $linea['id'] }}">@if(isset($linea['componente_id']))<input type="hidden" name="detalles[{{ $loop->index }}][despacho_componente_id]" value="{{ $linea['componente_id'] }}">@endif<div class="entrega-grid">
                        <label>Diferencia de aceptadas<input type="number" name="detalles[{{ $loop->index }}][aceptadas]" value="0" step="1" min="-10000000" max="10000000" required></label><label>Diferencia de rechazadas<input type="number" name="detalles[{{ $loop->index }}][rechazadas]" value="0" step="1" min="-10000000" max="10000000" required></label>
                    </div></fieldset>
                @endforeach
                <label>Motivo documentado de la rectificación<textarea name="observaciones" minlength="10" maxlength="1000" required></textarea></label><button type="submit" class="btn btn-secondary">Registrar rectificación con auditoría</button>
            </form>
        </details>
    @endif
</section>
