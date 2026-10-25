@extends('layouts.app')
@section('title', 'Indicadores y CSV')
@section('page-title', 'Indicadores y CSV')
@push('styles')<link rel="stylesheet" href="{{ asset('css/indicadores.css') }}">@endpush
@push('scripts')<script src="{{ asset('js/indicadores.js') }}" defer></script>@endpush
@section('content')
<div class="indicadores">
<div class="page-header">
    <div><h1>Indicadores y exportación CSV</h1><p>Actividad, situación actual y existencias con referencias verificables.</p></div>
</div>
<form method="GET" action="{{ route('indicadores.index') }}" class="card" data-indicadores data-filtros='@json(\App\Services\FiltrosIndicadores::APLICABLES)'>
    <div class="form-grid">
        <div class="form-group"><label for="reporte">Reporte</label><select name="reporte" id="reporte">@foreach($reportes as $clave => $definicion)<option value="{{ $clave }}" @selected($filtros['reporte'] === $clave)>{{ $definicion[0] }}</option>@endforeach</select></div>
        <div class="form-group"><label for="desde">Desde (vacío: sin límite inferior)</label><input type="date" name="desde" id="desde" value="{{ $filtros['desde'] }}"></div>
        <div class="form-group"><label for="hasta">Hasta</label><input type="date" name="hasta" id="hasta" value="{{ $filtros['hasta'] }}" required></div>
    </div>
    <details><summary>Filtros avanzados y referencias de trazabilidad</summary><p>Usa los identificadores que aparecen en los listados y reportes. Solo se muestran filtros aplicables al reporte seleccionado.</p><div class="form-grid">
        @foreach(['pedido_id'=>'Pedido ID','producto_id'=>'Producto ID','presentacion_id'=>'Presentación ID','insumo_id'=>'Insumo ID','tamano'=>'Tamaño del paquete'] as $clave => $etiqueta)
            <div class="form-group" data-filtro><label for="{{ $clave }}">{{ $etiqueta }}</label><input type="number" id="{{ $clave }}" name="{{ $clave }}" value="{{ $filtros[$clave] ?? '' }}" min="{{ $clave === 'tamano' ? 2 : 1 }}" @if($clave === 'tamano') max="1000" @endif></div>
        @endforeach
        <div class="form-group" data-filtro><label for="sku">SKU físico (no aplica al importe comercial)</label><input id="sku" name="sku" value="{{ $filtros['sku'] ?? '' }}" maxlength="50"></div>
        <div class="form-group" data-filtro><label for="estado">Estado actual del pedido</label><select id="estado" name="estado"><option value="">Todos</option>@foreach(['registrado','observado','confirmado','en_preparacion','listo_despacho','despachado','entrega_pendiente','entrega_parcial','rechazado','entregado','cancelado'] as $estado)<option @selected(($filtros['estado'] ?? '') === $estado) value="{{ $estado }}">{{ ucfirst(str_replace('_',' ',$estado)) }}</option>@endforeach</select></div>
        @foreach(['modalidad'=>['unidad'=>'Unidad','empaque'=>'Paquete normal','surtido'=>'Paquete surtido'],'composicion'=>['pendiente'=>'Pendiente','definida'=>'Definida'],'vinculacion'=>['general'=>'Envasado general','vinculado'=>'Envasado vinculado'],'tipo'=>['entrada'=>'Entrada','salida'=>'Salida','ajuste'=>'Ajuste'],'origen'=>['manual'=>'Manual','despacho'=>'Despacho','envasado'=>'Envasado','correccion_envasado'=>'Corrección de envasado','retorno_pedido'=>'Retorno aprobado']] as $clave=>$opciones)
        <div class="form-group" data-filtro><label for="{{ $clave }}">{{ ucfirst($clave) }}</label><select id="{{ $clave }}" name="{{ $clave }}"><option value="">Todos</option>@foreach($opciones as $valor=>$texto)<option value="{{ $valor }}" @selected(($filtros[$clave] ?? '') === $valor)>{{ $texto }}</option>@endforeach</select></div>
        @endforeach
    </div></details>
    <p data-filtro><label><input type="checkbox" name="historial" value="1" @checked($filtros['historial'])> Incluir versiones históricas en composiciones (no se suman)</label></p>
    @if(auth()->user()->role?->tienePermiso('indicadores.economicos.ver'))<p><label><input type="checkbox" name="economico" value="1" @checked($filtros['economico'])> Mostrar importes informativos en céntimos PEN; no son cobros</label></p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <button type="submit" class="btn btn-primary">Consultar indicadores</button>
    <a class="btn" href="{{ route('indicadores.index') }}">Restablecer filtros</a>
</form>
<section class="card" aria-label="Alcance del reporte">
    <h2>{{ $reportes[$filtros['reporte']][0] }}</h2><p>{{ $reportes[$filtros['reporte']][1] }}</p>
    <p>Referencia temporal: America/Lima. Generado: {{ $generado }}. {{ $resultado['cantidad'] }} filas. No se reconstruyen estados comerciales históricos no conservados.</p>
    <p>La exportación utiliza las mismas reglas y filtros sobre una nueva lectura. Si se registran operaciones después, sus resultados pueden cambiar; consulta la hora de generación del CSV.</p>
    @if(auth()->user()->role?->tienePermiso('indicadores.exportar'))<div class="indicadores-acciones"><a class="btn btn-primary" href="{{ route('indicadores.exportar', array_diff_key($filtros, ['pagina'=>true])) }}">Exportar detalle CSV completo</a><a class="btn" href="{{ route('indicadores.exportar', array_replace(array_diff_key($filtros, ['pagina'=>true]),['resumen'=>1])) }}">Exportar resumen CSV</a></div>@endif
    <div class="indicadores-totales">
    @foreach($resultado['comercial'] as $campo=>$valor)<p><strong>{{ \App\Services\FilasIndicadores::titulo($campo) }}:</strong> {{ $valor }}</p>@endforeach
    @foreach($resultado['totales'] as $campo=>$valor)<p><strong>{{ \App\Services\FilasIndicadores::titulo($campo) }} ({{ isset($resultado['noDisponibles'][$campo]) ? 'subtotal conocido' : 'total filtrado' }}):</strong> {{ $valor ?? 'No disponible' }}</p>@endforeach
    </div>
    @foreach($resultado['noDisponibles'] as $campo=>$cantidad)<p>{{ \App\Services\FilasIndicadores::titulo($campo) }}: {{ $cantidad }} referencias sin datos verificables. No se sustituyen por cero.</p>@endforeach
    <p>Los paquetes, las unidades físicas, los importes y las unidades de medida diferentes no se suman entre sí. El historial de composiciones no tiene total acumulable.</p>
</section>
<div class="table-container"><table><thead><tr>@foreach($campos as $campo)<th>{{ \App\Services\FilasIndicadores::titulo($campo) }}</th>@endforeach</tr></thead><tbody>
@forelse($resultado['filas'] as $fila)<tr>@foreach($campos as $campo)<td data-campo="{{ $campo }}">{{ $fila[$campo] ?? 'No disponible' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($campos) }}">No hay registros para los filtros seleccionados.</td></tr>@endforelse
</tbody></table></div>
<nav aria-label="Páginas del reporte">
    @if($filtros['pagina']>1)<a class="btn" href="{{ route('indicadores.index', array_replace($filtros,['pagina'=>$filtros['pagina']-1])) }}">Anterior</a>@endif
    <span>Página {{ $filtros['pagina'] }} de {{ max(1,(int)ceil($resultado['cantidad']/25)) }}</span>
    @if($filtros['pagina']*25<$resultado['cantidad'])<a class="btn" href="{{ route('indicadores.index', array_replace($filtros,['pagina'=>$filtros['pagina']+1])) }}">Siguiente</a>@endif
</nav>
</div>
@endsection
