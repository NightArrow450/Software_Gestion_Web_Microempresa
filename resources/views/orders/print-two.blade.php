<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notas de pedido{{ !empty($fechaImpresion) ? " del ".\Carbon\Carbon::parse($fechaImpresion)->format("d/m/Y") : "" }} - DHACER ECO LIMP</title>
<link rel="stylesheet" href="{{ asset('css/nota-pedido.css') }}">
</head>
<body>
<div class="print-controls"><button onclick="window.print()">Imprimir {{ $pedidos->count() }} nota(s) en A4 horizontal</button>
    <p>Selecciona A4 · Horizontal · Escala 100 % · Desactivar encabezados y pies del navegador. Dos notas diferentes por hoja. Total: {{ $pedidos->count() }} nota(s) / {{ $pedidos->chunk(2)->count() }} hoja(s). No se incluyen pedidos cancelados.</p>
</div>
@foreach($pedidos->chunk(2) as $par)
<div class="print-preview" tabindex="0" role="region" aria-label="Vista previa de notas de pedido"><main class="a4-hoja">
@foreach($par as $pedido)
<section class="nota" aria-label="Nota N.º {{ $pedido->numero }}">
    <header class="nota-encabezado">
        <div class="nota-logo"><img src="{{ asset('images/logo.png') }}" alt="Eco Limp"><small>Los profesionales en productos de limpieza</small></div>
        <div class="nota-empresa"><div class="nota-titulo">DHACER ECO LIMP S.R.L.</div>
            <div class="nota-lema">"LOS PROFESIONALES DE PRODUCTO DE LIMPIEZA"</div>
            <div class="nota-productos">PRODUCTOS: LEJÍA · PERFUMADORES · LIMPIA TODO ·<br>JABÓN LÍQUIDO · LIMPIA VIDRIOS · SACAGRASA<br>SILICONA MULTIUSO · SHAMPO CON CERA, ENTRE OTROS</div>
            <div class="nota-direccion">DIRECCIÓN: LIMA-LIMA-PERÚ</div>
            <div class="nota-contacto">TELÉFONO: 955 456 933 - 989 466 158 - 937 431 291</div>
        </div>
        <div class="nota-lateral"><strong>NOTA DE PEDIDO</strong><div>FECHA: {{ $pedido->fecha_pedido->format('d/m/Y') }}</div>
            <div>N.º {{ $pedido->numero }}</div></div>
    </header>
    <div class="nota-franja"><span>VENDEDOR: {{ strtoupper($pedido->vendedor?->first_name.' '.$pedido->vendedor?->last_name) }}</span><span>CLIENTE: {{ strtoupper($pedido->cliente_nombre) }}</span></div>
    <div class="nota-datos">
        <div><b>MERCADO:</b> {{ $pedido->cliente_mercado ?: '—' }}</div>
        <div><b>N.º PUESTO/REFERENCIA:</b> {{ $pedido->cliente_puesto_referencia ?: '—' }}</div>
        <div><b>DISTRITO:</b> {{ $pedido->cliente_distrito ?: '—' }}</div>
        <div><b>TELÉFONO:</b> {{ $pedido->cliente_telefono ?: '—' }}</div>
        <div><b>TÉRMINO:</b> {{ $pedido->condicion_pago === 'credito' ? 'AL CRÉDITO' : 'AL CONTADO' }}</div>
        <div><b>RUC/DNI:</b> {{ $pedido->cliente_documento ?: '—' }}</div>
        <div><b>DIRECCIÓN:</b> {{ $pedido->cliente_direccion ?: '—' }}</div>
        <div><b>ENTREGA:</b> {{ $pedido->fecha_entrega->format('d/m/Y') }}</div>
    </div>
    <div class="table-container"><table class="nota-tabla"><colgroup><col class="cant"><col class="desc"><col class="precio"><col class="importe"></colgroup>
        <thead><tr><th>CANTIDAD</th><th>DESCRIPCIÓN DEL PRODUCTO</th><th>P.U.</th><th>IMPORTE</th></tr></thead>
        <tbody>
        @foreach($pedido->detalles->take(18) as $detalle)<tr><td class="centrado">{{ $detalle->cantidad }}</td>
            <td>{{ $detalle->descripcion_producto }} {{ $detalle->modalidad === 'empaque' ? '('.$detalle->descripcionModalidad().')' : '' }}</td>
            <td class="derecha">{{ number_format((float)$detalle->precio_unitario,2) }}</td>
            <td class="derecha">{{ number_format((float)$detalle->importe,2) }}</td></tr>@endforeach
        @for($i=$pedido->detalles->take(18)->count();$i<18;$i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td></tr>
        @endfor
        </tbody><tfoot><tr><td colspan="2" rowspan="1" class="sin-borde" style="border-left:0;border-bottom:0"></td>
            <th>TOTAL S/</th><td class="derecha total-numero">{{ number_format((float)$pedido->total,2) }}</td></tr></tfoot>
    </table></div>
    <footer class="nota-pie"><div class="nota-qr"><img src="{{ asset('images/qr-contacto.png') }}" alt="QR de contacto"><b>QR: CONTACTO</b></div>
        <div class="firma"><div></div><span>AUTORIZADO POR:</span></div><div class="firma"><div></div><span>RECIBIDO POR:</span></div></footer>
    <div class="nota-continuacion">@if($pedido->detalles->count()>18) La nota muestra 18 de {{ $pedido->detalles->count() }} ítems. Consulta el pedido digital para el detalle completo. @endif</div>
</section>
@endforeach
</main></div>
@endforeach
</body>
</html>
