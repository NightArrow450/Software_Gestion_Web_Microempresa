<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dos notas de pedido — DHACER ECO LIMP</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111;
            font-family: Arial, sans-serif;
        }

        .herramientas {
            padding: 12px;
            background: #e5e7eb;
            text-align: center;
        }

        .herramientas button {
            border: 0;
            background: #006948;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        .hoja {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm;
            padding: 4mm;
            background: white;
        }

        .nota {
            border: 1px solid #555;
            height: 190mm;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .cabecera {
            height: 43mm;
            position: relative;
            text-align: center;
            padding: 3mm 2mm 1mm 32mm;
        }

        .cabecera img {
            position: absolute;
            width: 28mm;
            max-height: 28mm;
            object-fit: contain;
            left: 2mm;
            top: 4mm;
        }

        .cabecera h1 {
            margin: 4mm 0 1mm;
            font-size: 12px;
        }

        .cabecera p {
            margin: 1mm 0;
            font-size: 7px;
            font-weight: bold;
        }

        .cabecera .titulo {
            position: absolute;
            right: 3mm;
            top: 2mm;
            font-size: 9px;
            font-weight: 800;
        }

        .cabecera .fecha {
            margin-top: 2mm;
            font-size: 8px;
            text-align: right;
        }

        .barra {
            display: flex;
            justify-content: space-between;
            background: #868686;
            color: white;
            padding: 1.2mm 2mm;
            font-weight: 700;
            font-size: 7px;
        }

        .cliente {
            min-height: 27mm;
            padding: 2mm 2mm 1mm;
            font-size: 8px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .cliente strong {
            display: inline-block;
            min-width: 19mm;
        }

        .tabla {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7px;
        }

        .tabla th {
            background: #888;
            color: #fff;
            height: 6mm;
        }

        .tabla th,
        .tabla td {
            border: 1px solid #555;
            padding: 0.6mm 1mm;
        }

        .tabla td {
            height: 5mm;
            vertical-align: middle;
        }

        .tabla .cantidad {
            width: 12%;
            text-align: center;
        }

        .tabla .descripcion {
            width: 59%;
        }

        .tabla .precio {
            width: 14%;
            text-align: right;
        }

        .tabla .importe {
            width: 15%;
            text-align: right;
        }

        .tabla .total {
            background: #888;
            color: white;
            text-align: center;
            font-weight: bold;
        }

        .pie {
            margin-top: auto;
            height: 25mm;
            padding: 2mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 3mm;
            font-size: 7px;
            text-align: center;
        }

        .qr {
            width: 19mm;
            height: 19mm;
            object-fit: contain;
        }

        .firma {
            border-top: 1px solid #444;
            flex: 1;
            padding-top: 1mm;
            font-weight: bold;
        }

        .qrtexto {
            width: 20mm;
            font-weight: bold;
        }

        @media print {
            .herramientas {
                display: none;
            }

            .hoja {
                padding: 0;
            }
        }

        @media screen {
            body {
                background: #f3f4f6;
            }

            .hoja {
                max-width: 1100px;
                margin: 20px auto;
            }
        }
    </style>
</head>

<body>

<div class="herramientas">
    <button onclick="window.print()">
        Imprimir dos pedidos en A4 horizontal
    </button>
</div>

<div class="hoja">

@foreach($pedidos as $pedido)
    <article class="nota">

        <header class="cabecera">
            <img
                src="{{ asset('images/logo.png') }}"
                alt="Eco Limp"
            >

            <div class="titulo">
                NOTA DE PEDIDO
            </div>

            <h1>
                DHACER ECO LIMP S.R.L.
            </h1>

            <p>
                "LOS PROFESIONALES DE PRODUCTOS DE LIMPIEZA"
            </p>

            <p>
                PRODUCTOS: LEJÍA - PERFUMADORES - LIMPIA TODO -
            </p>

            <p>
                JABÓN LÍQUIDO - LIMPIA VIDRIOS - SACAGRASA
            </p>

            <p>
                SILICONA MULTIUSO - SHAMPO CON CERA, ENTRE OTROS
            </p>

            <p>
                DIRECCIÓN: LIMA - LIMA - PERÚ
            </p>

            <p>
                TELÉFONO: 955 456 933 - 989 466 158 - 937 431 291
            </p>

            <div class="fecha">
                FECHA: {{ $pedido->fecha_pedido->format('d/m/Y') }}

                &nbsp;&nbsp;

                N.º {{ $pedido->numero }}
            </div>
        </header>

        <div class="barra">
            <span>
                VENDEDOR:
                {{ strtoupper($pedido->vendedor?->first_name ?? '') }}
            </span>

            <span>
                CLIENTE: {{ $pedido->cliente_nombre }}
            </span>
        </div>

        <div class="cliente">
            <div>
                <strong>MERCADO:</strong>
                {{ $pedido->cliente_mercado ?: '—' }}
            </div>

            <div>
                <strong>N.º PUESTO:</strong>
                {{ $pedido->cliente_puesto_referencia ?: '—' }}
            </div>

            <div>
                <strong>DISTRITO:</strong>
                {{ $pedido->cliente_distrito ?: '—' }}
            </div>

            <div>
                <strong>DIRECCIÓN:</strong>
                {{ $pedido->cliente_direccion ?: '—' }}
            </div>

            <div>
                <strong>TELÉFONO:</strong>
                {{ $pedido->cliente_telefono ?: '—' }}
            </div>

            <div>
                <strong>TÉRMINO:</strong>
                {{ strtoupper($pedido->condicion_pago) }}
            </div>

            <div>
                <strong>RUC/DNI:</strong>
                {{ $pedido->cliente_documento ?: '—' }}
            </div>
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="cantidad">
                        CANTIDAD
                    </th>

                    <th class="descripcion">
                        DESCRIPCIÓN DEL PRODUCTO
                    </th>

                    <th class="precio">
                        P.U.
                    </th>

                    <th class="importe">
                        IMPORTE
                    </th>
                </tr>
            </thead>

            <tbody>
                @for($i = 0; $i < 18; $i++)
                    @php($fila = $pedido->detalles->get($i))

                    <tr>
                        <td class="cantidad">
                            {{ $fila?->cantidad ?? '' }}
                        </td>

                        <td class="descripcion">
                            {{ $fila?->descripcion_producto ?? '' }}
                        </td>

                        <td class="precio">
                            {{ $fila ? number_format((float) $fila->precio_unitario, 2) : '' }}
                        </td>

                        <td class="importe">
                            {{ $fila ? number_format((float) $fila->importe, 2) : '' }}
                        </td>
                    </tr>
                @endfor

                <tr>
                    <td
                        colspan="2"
                        style="border-left:0;border-bottom:0;"
                    ></td>

                    <td class="total">
                        TOTAL S/
                    </td>

                    <td class="importe">
                        <strong>
                            {{ number_format((float) $pedido->total, 2) }}
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <footer class="pie">
            <div class="qrtexto">

                @if(is_file(public_path('images/qr-contacto.png')))
                    <img
                        class="qr"
                        src="{{ asset('images/qr-contacto.png') }}"
                        alt="QR de contacto"
                    >
                @else
                    <div
                        style="height:19mm;display:grid;place-items:center;"
                    >
                        QR CONTACTO
                    </div>
                @endif

                QR: CONTACTO
            </div>

            <div class="firma">
                AUTORIZADO POR:
            </div>

            <div class="firma">
                RECIBIDO POR:
            </div>
        </footer>

    </article>
@endforeach

</div>

</body>
</html>
