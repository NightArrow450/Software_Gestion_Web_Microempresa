@extends('layouts.app')

@section('title', 'Detalle de Pedido')
@section('page-title', 'Detalle de Pedido')
@section('breadcrumb', 'Inicio / Pedidos / Detalle')

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">Nota de pedido</div>

        <h1>Pedido N.º {{ $pedido->numero }}</h1>

        <p>
            Estado:
            <strong>{{ ucfirst($pedido->estado) }}</strong>
        </p>
    </div>

    <a
        class="btn btn-secondary"
        href="{{ route('pedidos.index') }}"
    >
        <span class="material-symbols-outlined">
            arrow_back
        </span>
        Volver
    </a>
</div>

<section class="form-section">
    <h2 class="form-section-title">
        Datos generales
    </h2>

    <div class="form-grid">
        <div>
            <span class="muted">Cliente</span>
            <br>
            <strong>{{ $pedido->cliente_nombre }}</strong>
        </div>

        <div>
            <span class="muted">Documento</span>
            <br>
            {{ $pedido->cliente_documento ?: '—' }}
        </div>

        <div>
            <span class="muted">Teléfono</span>
            <br>
            {{ $pedido->cliente_telefono ?: '—' }}
        </div>

        <div>
            <span class="muted">Mercado / puesto</span>
            <br>
            {{ $pedido->cliente_mercado ?: '—' }}
            /
            {{ $pedido->cliente_puesto_referencia ?: '—' }}
        </div>

        <div>
            <span class="muted">Distrito</span>
            <br>
            {{ $pedido->cliente_distrito ?: '—' }}
        </div>

        <div>
            <span class="muted">Dirección</span>
            <br>
            {{ $pedido->cliente_direccion ?: '—' }}
        </div>

        <div>
            <span class="muted">Vendedor</span>
            <br>
            {{ $pedido->vendedor?->first_name }}
            {{ $pedido->vendedor?->last_name }}
        </div>

        <div>
            <span class="muted">Condición de pago</span>
            <br>
            {{ ucfirst($pedido->condicion_pago) }}
        </div>

        <div>
            <span class="muted">Fecha de pedido</span>
            <br>
            {{ $pedido->fecha_pedido->format('d/m/Y') }}
        </div>

        <div>
            <span class="muted">Fecha de entrega</span>
            <br>
            {{ $pedido->fecha_entrega->format('d/m/Y') }}
        </div>
    </div>
</section>

<section class="table-card" style="margin-top:22px;">
    <div class="table-header">
        <strong>Productos solicitados</strong>
    </div>

    <div class="table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Descripción</th>
                    <th>Modalidad</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Importe</th>
                </tr>
            </thead>

            <tbody>
                @foreach($pedido->detalles as $detalle)
                    <tr>
                        <td>
                            {{ $detalle->codigo_sku }}
                        </td>

                        <td>
                            {{ $detalle->descripcion_producto }}
                        </td>

                        <td>
                            {{ ucfirst($detalle->modalidad) }}
                        </td>

                        <td>
                            {{ $detalle->cantidad }}
                        </td>

                        <td>
                            S/ {{ number_format((float) $detalle->precio_unitario, 2) }}
                        </td>

                        <td>
                            S/ {{ number_format((float) $detalle->importe, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <th
                        colspan="5"
                        style="text-align:right;"
                    >
                        TOTAL
                    </th>

                    <th>
                        S/ {{ number_format((float) $pedido->total, 2) }}
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

@if($pedido->observaciones)
    <section class="form-section" style="margin-top:22px;">
        <h2 class="form-section-title">
            Observaciones
        </h2>

        <p>{{ $pedido->observaciones }}</p>
    </section>
@endif

<div class="muted" style="margin-top:16px;">
    El procesamiento y los nuevos estados se implementarán
    en HU-02 y HU-03.
</div>
@endsection
