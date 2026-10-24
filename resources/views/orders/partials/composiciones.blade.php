@foreach($pedido->detalles->where('tipo_linea', 'surtido') as $lineaSurtida)
<section class="surtidos-historial" aria-label="Composición individual de paquetes surtidos">
<h3>{{ $lineaSurtida->descripcion_producto }}</h3>
@php($paquetesSurtidos = app(\App\Services\GestionarSurtidos::class)->paquetes($lineaSurtida))
@foreach($paquetesSurtidos as $paqueteSurtido)
<div class="surtido-paquete">
<p><strong>Paquete {{ $paqueteSurtido['numero'] }} · {{ $paqueteSurtido['estado'] === 'definida' ? 'Composición definida' : 'Pendiente de composición' }}</strong> · versión {{ $paqueteSurtido['version'] }}</p>
<ul>@foreach($paqueteSurtido['componentes'] as $componenteSurtido)<li>{{ $componenteSurtido['cantidad'] }} unidades · {{ $componenteSurtido['descripcion'] }} · {{ $componenteSurtido['sku'] }}</li>@endforeach</ul>
</div>
@endforeach
@if(!($soloLectura ?? false))
<details><summary>Historial de composiciones</summary>
@foreach(app(\App\Services\GestionarSurtidos::class)->historial($lineaSurtida) as $revisionSurtida)
<p>Paquete {{ $revisionSurtida['numero'] }} · versión {{ $revisionSurtida['version'] }} · {{ $revisionSurtida['first_name'] }} {{ $revisionSurtida['last_name'] }} · {{ $revisionSurtida['created_at'] }} · {{ $revisionSurtida['estado'] }}</p><p>{{ $revisionSurtida['motivo'] }}</p>
<ul>@foreach($revisionSurtida['componentes'] as $c)<li>{{ $c->cantidad }} unidades · {{ $c->descripcion }} · {{ $c->sku }}</li>@endforeach</ul>
@endforeach</details>
@endif
@if(!($soloLectura ?? false) && auth()->user()?->role?->tienePermiso('pedidos.surtidos.componer') && in_array($pedido->estado, ['registrado','observado','confirmado','en_preparacion'], true))
<details><summary>Definir o modificar composiciones con historial</summary>
<form method="POST" action="{{ route('surtidos.componer', [$pedido, $lineaSurtida]) }}">
@csrf @include('components.token-operacion')<input type="hidden" name="version" value="{{ $pedido->version }}">
<input type="hidden" name="paquetes_json" id="composicion-{{ $lineaSurtida->id }}" value="{{ json_encode($paquetesSurtidos) }}">
@php($formatoEditor = collect(app(\App\Services\GestionarSurtidos::class)->formatos([$lineaSurtida->formato_surtido_id]))->firstWhere('id', $lineaSurtida->formato_surtido_id))
@if($formatoEditor)
<div data-editor-surtido data-input="composicion-{{ $lineaSurtida->id }}" data-skus="{{ json_encode($formatoEditor['skus']) }}" data-tamano="{{ $lineaSurtida->unidades_por_empaque }}" data-cantidad="{{ $lineaSurtida->cantidad }}"></div>
<label>Motivo de definición o cambio<textarea class="form-control" name="motivo" minlength="10" maxlength="1000" required></textarea></label><button type="submit" class="btn btn-primary">Guardar composición con historial</button>
@else<p role="alert">La modalidad está inactiva. Solicita su revisión comercial; se conserva la composición histórica.</p>@endif
</form></details>
@endif
</section>
@endforeach
@once
@push('styles')<link rel="stylesheet" href="{{ asset('css/surtidos.css') }}">@endpush
@if(!($soloLectura ?? false)) @push('scripts')<script src="{{ asset('js/surtidos-utils.js') }}"></script><script src="{{ asset('js/surtidos.js') }}"></script>@endpush @endif
@endonce
