<section class="entrega-panel" aria-label="Resultado de entrega y retornos">
    <h2>Entrega, rechazos y retorno de productos</h2>
    <p>El rechazo no devuelve existencias. Solo una recepción física apta y su aprobación generan una entrada. No se devuelven materiales de envasado.</p>
    @if($resumenEntrega['historico_sin_desglose'])
        <p class="entrega-aviso">Entrega histórica sin desglose por línea. Se conserva el registro original; no se inventan cantidades aceptadas ni retornos.</p>
    @else
        @foreach($resumenEntrega['lineas'] as $linea)
            <article class="entrega-linea">
                <h3>{{ $linea['sku'] }} · {{ $linea['descripcion'] }}</h3>
                <p>Pedido original: {{ $linea['cantidad_original'] }} {{ $linea['modalidad'] === 'empaque' ? 'empaques de '.($linea['factor'] ?? 'tamaño no comprobado') : 'unidades' }}.</p>
                @if(!$linea['verificada'])<p class="entrega-aviso">Despacho histórico sin cantidades de inventario verificables: la reincorporación automática está bloqueada.</p>@endif
                <dl class="entrega-cifras">
                    @foreach(['despachadas' => 'Despachadas', 'aceptadas' => 'Aceptadas por el cliente', 'rechazadas' => 'Rechazadas', 'pendientes' => 'Pendientes de entrega', 'retornadas' => 'Recibidas físicamente', 'por_retornar' => 'Rechazadas pendientes de retorno', 'aptas' => 'Aptas recibidas', 'por_aprobar' => 'Aptas pendientes de aprobación', 'danadas' => 'Dañadas recibidas', 'por_revisar' => 'Recibidas en evaluación', 'no_recuperadas' => 'Perdidas o no recuperadas', 'reincorporadas' => 'Reincorporadas al stock'] as $campo => $etiqueta)
                        <div><dt>{{ $etiqueta }}</dt><dd>{{ $linea[$campo] ?? 'No comprobado' }}</dd></div>
                    @endforeach
                </dl>
                <p>Importe original: S/ {{ number_format($linea['importe_centimos'] / 100, 2) }}. Importes históricos informativos:
                    @foreach(['importe_aceptado_centimos' => 'Aceptado', 'importe_rechazado_centimos' => 'Rechazado', 'importe_pendiente_centimos' => 'Pendiente'] as $campo => $etiqueta)
                        <span>{{ $etiqueta }}: {{ $linea[$campo] === null ? 'No comprobado' : 'S/ '.number_format($linea[$campo] / 100, 2) }}.</span>
                    @endforeach
                </p>
            </article>
        @endforeach
    @endif
    <p>Estos importes no representan dinero cobrado ni reembolsado. Los céntimos se distribuyen proporcionalmente con el precio histórico, conservando el importe original. No se modifican pagos ni comprobantes.</p>
    @if($resumenEntrega['incidencia']?->resuelto_en)
        <p class="entrega-aviso">Expediente cerrado: {{ $resumenEntrega['incidencia']->resolucion }}</p>
    @endif
    @if($resumenEntrega['retornos']->isNotEmpty())
        <h3>Recepciones físicas y aprobaciones</h3>
        <div class="table-container"><table class="entrega-tabla"><thead><tr><th>Recepción / línea</th><th>Fecha del hecho</th><th>Aptas</th><th>Dañadas</th><th>En evaluación</th><th>No recuperadas</th><th>Reincorporadas</th></tr></thead><tbody>
            @foreach($resumenEntrega['retornos'] as $retorno)
                <tr><td>N.º {{ $retorno->retorno_id }} · {{ $resumenEntrega['lineas'][$retorno->pedido_detalle_id]['sku'] ?? 'Línea histórica' }}</td><td>{{ \Carbon\Carbon::parse($retorno->ocurrido_en)->timezone('America/Lima')->format('d/m/Y H:i') }}</td><td>{{ $retorno->aptas + $retorno->revisadas_aptas }}</td><td>{{ $retorno->danadas + $retorno->revisadas_danadas }}</td><td>{{ $retorno->en_revision - $retorno->revisadas_aptas - $retorno->revisadas_danadas }}</td><td>{{ $retorno->no_recuperadas }}</td><td>{{ $retorno->reincorporadas }}</td></tr>
            @endforeach
        </tbody></table></div>
    @endif
    <h3>Historial de incidencias y devoluciones</h3>
    <ol class="entrega-historial">
        @forelse($resumenEntrega['eventos'] as $evento)
            <li>
                <strong>{{ match($evento->tipo) { 'despacho_base' => 'Fotografía histórica del despacho', 'resultado_entrega' => 'Resultado de entrega', 'retorno_registrado' => 'Recepción o no recuperación registrada', 'retorno_aprobado' => 'Reincorporación aprobada', 'rectificacion_entrega' => 'Rectificación autorizada', 'incidencia_clasificar' => 'Clasificación física', 'incidencia_revisar' => 'Revisión comercial', 'incidencia_resolver' => 'Resolución comercial', 'incidencia_reabrir' => 'Reapertura autorizada', default => 'Operación de entrega' } }} · operación {{ $evento->id }}</strong>
                <p>{{ \Carbon\Carbon::parse($evento->created_at)->timezone('America/Lima')->format('d/m/Y H:i') }} · Registró: {{ $evento->first_name }} {{ $evento->last_name }}</p>
                <p>{{ $evento->motivo }}</p>
                @php($datosEvento = json_decode($evento->datos, true))
                @if(!empty($datosEvento['ocurrido_en']))<p>Fecha informada del hecho: {{ $datosEvento['ocurrido_en'] }}</p>@endif
                @foreach($datosEvento['detalles'] ?? [] as $detalleEvento)
                    <p>{{ $resumenEntrega['lineas'][$detalleEvento['pedido_detalle_id']]['sku'] ?? 'Línea histórica' }}:
                        @foreach(['aceptadas' => 'aceptadas', 'rechazadas' => 'rechazadas', 'aptas' => 'aptas', 'danadas' => 'dañadas', 'en_revision' => 'en evaluación', 'no_recuperadas' => 'no recuperadas'] as $campo => $etiqueta)
                            @if(isset($detalleEvento[$campo])) {{ $etiqueta }} {{ $detalleEvento[$campo] }}; @endif
                        @endforeach
                        {{ $detalleEvento['motivo'] ?? '' }}
                    </p>
                @endforeach
                @if(isset($datosEvento['cantidad']))<p>Unidades aprobadas: {{ $datosEvento['cantidad'] }}. La entrada de inventario identifica esta operación como origen.</p>@endif
            </li>
        @empty
            <li>No hay operaciones de entrega o retorno registradas.</li>
        @endforelse
    </ol>
</section>
