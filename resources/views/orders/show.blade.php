@extends('layouts.app')

@section('title', 'Detalle del Pedido')
@section('page-title', 'Detalle del Pedido')
@section('breadcrumb', 'Inicio / Pedidos / Detalle')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sprint2.css') }}">
@endpush

@section('content')
@php
    $permisos = auth()->user()
        ->role?->permisos?->pluck('nombre')->all() ?? [];

    $rolActual = auth()->user()->role?->nombre;

    $puedeEditar = in_array(
        'pedidos.editar',
        $permisos,
        true
    );

    $puedeCancelar = in_array(
        'pedidos.cancelar',
        $permisos,
        true
    ) && in_array(
        $rolActual,
        ['Administrador', 'Operaciones Comerciales'],
        true
    );

    $editable = true;
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">Nota de pedido</div>

        <h1>Pedido N.º {{ $pedido->numero }}</h1>

        <p>
            Registrado el {{ $pedido->fecha_pedido->format('d/m/Y') }}
            · Entrega prevista:
            {{ $pedido->fecha_entrega->format('d/m/Y') }}
        </p>
    </div>

    <div class="s2-actions">
        <a
            class="btn btn-secondary"
            href="{{ route('pedidos.index') }}"
        >
            <span class="material-symbols-outlined">
                arrow_back
            </span>
            Volver
        </a>

        <button type="button" class="btn btn-secondary" id="copiar-resumen-whatsapp">
            <span class="material-symbols-outlined">content_copy</span>
            Copiar resumen para WhatsApp
        </button>

        @if($editable && $puedeEditar)
            <a
                class="btn btn-primary"
                href="{{ route('pedidos.edit', $pedido) }}"
            >
                <span class="material-symbols-outlined">
                    edit
                </span>
                Editar pedido
            </a>
        @endif
    </div>
</div>
<p id="estado-copia-whatsapp" class="s2-note" role="status" aria-live="polite"></p>
<textarea id="resumen-whatsapp-manual" class="form-control" rows="8" readonly hidden aria-label="Resumen del pedido para copiar manualmente"></textarea>

<div class="s2-summary">
    <span class="s2-state">
        {{ \App\EstadoPedido::etiquetas()[$pedido->estado] ?? ucfirst(str_replace('_', ' ', $pedido->estado)) }}
    </span>

    <strong>
        S/ {{ number_format((float) $pedido->total, 2) }}
    </strong>

    <span class="muted">
        Vendedor:
        {{ $pedido->vendedor?->first_name }}
        {{ $pedido->vendedor?->last_name }}
    </span>
</div>

<section class="form-section">
    <h2 class="form-section-title">Datos del pedido</h2>

    <div class="s2-detail-grid">
        @foreach([
            'Cliente' => $pedido->cliente_nombre,
            'RUC / DNI' => $pedido->cliente_documento,
            'Teléfono' => $pedido->cliente_telefono,
            'Mercado' => $pedido->cliente_mercado,
            'Puesto / referencia' =>
                $pedido->cliente_puesto_referencia,
            'Distrito' => $pedido->cliente_distrito,
            'Dirección' => $pedido->cliente_direccion,
            'Condición de pago' =>
                $pedido->condicion_pago === 'credito'
                    ? 'Crédito'
                    : 'Al contado',
            'Observaciones' => $pedido->observaciones,
        ] as $etiqueta => $dato)
            <div>
                <div class="detail-label">
                    {{ $etiqueta }}
                </div>

                <div class="detail-value">
                    {{ $dato ?: '—' }}
                </div>
            </div>
        @endforeach
    </div>
</section>

<section class="table-card">
    <div class="table-header">
        <strong>Detalle de productos</strong>
    </div>

    <div class="table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Producto / Presentación</th>
                    <th>Modalidad</th>
                    <th>Cantidad</th>
                    <th>P. Unitario</th>
                    <th>Importe</th>
                </tr>
            </thead>

            <tbody>
                @foreach($pedido->detalles as $detalle)
                    <tr>
                        <td>{{ $detalle->codigo_sku }}</td>

                        <td>
                            {{ $detalle->descripcion_producto }}
                            @if($detalle->cantidad_fisica_ajustada !== null)<p>Ajuste comercial explícito: {{ $detalle->cantidad_fisica_ajustada }} unidades físicas · importe revisado S/ {{ $detalle->importe }}</p>@endif
                        </td>

                        <td>
                            {{ $detalle->descripcionModalidad() }}
                        </td>

                        <td>{{ $detalle->cantidad }}</td>

                        <td>
                            S/ {{ number_format((float) $detalle->precio_unitario, 2) }}
                        </td>

                        <td>
                            S/ {{ number_format((float) $detalle->importe, 2) }}
                        </td>
                    </tr>
                @endforeach

                <tr>
                    <td colspan="5" style="text-align:right;">
                        <strong>TOTAL</strong>
                    </td>

                    <td>
                        <strong>
                            S/ {{ number_format((float) $pedido->total, 2) }}
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

@if($puedeEditar && in_array($pedido->estado, ['registrado', 'observado'], true))
    <section class="form-section">
        <h2 class="form-section-title">
            Procesar pedido (HU-02)
        </h2>

        <p class="s2-note">
            Confirma el pedido si está correcto o regístralo
            como observado, indicando un motivo.
        </p>

        <div
            class="s2-panel-actions"
            style="margin-top:16px;"
        >
            <form
                action="{{ route('pedidos.procesar', $pedido) }}"
                method="POST"
            >
                @csrf @include('components.token-operacion')

                <input
                    type="hidden"
                    name="accion"
                    value="confirmar"
                >

                <button
                    class="btn btn-primary"
                    type="submit"
                    onclick="return confirm('¿Confirmar este pedido para continuar su atención?')"
                >
                    <span class="material-symbols-outlined">
                        check_circle
                    </span>
                    Confirmar pedido
                </button>
            </form>

            @if($pedido->estado === 'registrado')
                <form
                    action="{{ route('pedidos.procesar', $pedido) }}"
                    method="POST"
                >
                    @csrf @include('components.token-operacion')

                    <input
                        type="hidden"
                        name="accion"
                        value="observar"
                    >

                    <input
                        class="form-control"
                        name="motivo"
                        required
                        minlength="10" maxlength="1000"
                        placeholder="Motivo de observación"
                    >

                    <button
                        class="btn btn-secondary"
                        type="submit"
                    >
                        <span class="material-symbols-outlined">
                            report
                        </span>
                        Observar
                    </button>
                </form>
            @endif
        </div>
    </section>
@endif

@if(
    $puedeCancelar &&
    in_array(
        $pedido->estado,
        ['registrado', 'observado', 'confirmado'],
        true
    )
)
    <section class="form-section">
        <h2 class="form-section-title">
            Cancelación con trazabilidad
        </h2>

        <p class="s2-note">
            Administración y Operaciones Comerciales pueden
            cancelar pedidos indicando un motivo obligatorio.
        </p>

        <form
            action="{{ route('pedidos.cancelar', $pedido) }}"
            method="POST"
            class="s2-panel-actions"
        >
            @csrf @include('components.token-operacion')

            <input
                class="form-control"
                name="motivo"
                required
                minlength="10" maxlength="1000"
                placeholder="Motivo obligatorio de cancelación"
                value="{{ old('motivo') }}"
            >

            <button
                class="btn btn-secondary"
                style="border-color:#b42318;color:#b42318;"
                type="submit"
                onclick="return confirm('¿Cancelar el pedido? No se eliminará el historial.')"
            >
                Cancelar pedido
            </button>
        </form>
    </section>
@endif


<section class="form-section">
    <h2 class="form-section-title">Historial de estados</h2>
    @php($etiquetasEstado = \App\EstadoPedido::etiquetas())

    <div class="table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Estado anterior</th>
                    <th>Estado nuevo</th>
                    <th>Responsable</th>
                    <th>Motivo</th>
                </tr>
            </thead>

            <tbody>
                @foreach($pedido->historialEstados as $evento)
                    <tr>
                        <td>
                            {{ $evento->created_at?->timezone('America/Lima')->format('d/m/Y H:i') }}
                        </td>

                        <td>
                            {{ $etiquetasEstado[$evento->estado_anterior] ?? ($evento->estado_anterior ? ucfirst(str_replace('_', ' ', $evento->estado_anterior)) : '—') }}
                        </td>

                        <td>
                            <strong>
                                {{ $etiquetasEstado[$evento->estado_nuevo] ?? ucfirst(str_replace('_', ' ', $evento->estado_nuevo)) }}
                            </strong>
                        </td>

                        <td>
                            {{ $evento->usuario?->first_name }}
                            {{ $evento->usuario?->last_name }}
                        </td>

                        <td>
                            {{ $evento->motivo ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="s2-note">
        La interfaz ampliada de seguimiento y trazabilidad
        corresponde al Sprint 5.
    </p>
</section>

<section class="form-section">
    <h2 class="form-section-title">
        Imprimir en formato original
    </h2>

    <p class="s2-note">
        Selecciona otra nota desde
        <a
            href="{{ route('pedidos.index') }}"
            style="color:var(--primary);"
        >
            Gestión de Pedidos
        </a>
        para imprimir dos pedidos distintos juntos
        en hoja A4 horizontal.
    </p>
</section>
@include('orders.partials.agrupaciones')
@include('orders.partials.versiones')
@include('orders.partials.retiro-faltantes')
@endsection

@push('scripts')
<script>
(() => {
    'use strict';
    const boton = document.getElementById('copiar-resumen-whatsapp');
    if (!boton) return;
    const resumen = {{ Illuminate\Support\Js::from([
        'numero' => $pedido->numero,
        'cliente' => $pedido->cliente_nombre,
        'fecha' => $pedido->fecha_entrega?->format('d/m/Y'),
        'condicion' => $pedido->condicion_pago === 'credito' ? 'Crédito' : 'Al contado',
        'total' => number_format((float) $pedido->total, 2, '.', ''),
        'productos' => $pedido->detalles->map(fn($detalle) => [
            'descripcion' => $detalle->descripcion_producto,
            'paquetes' => [],
            'modalidad' => $detalle->modalidad,
            'empaque' => $detalle->descripcionModalidad(),
            'cantidad' => $detalle->cantidad,
            'importe' => number_format((float) $detalle->importe, 2, '.', ''),
        ])->values()->all(),
    ]) }};
    boton.addEventListener('click', async () => {
        const lineas = [
            `PEDIDO N.º ${resumen.numero}`,
            `Cliente: ${resumen.cliente}`,
            `Entrega prevista: ${resumen.fecha}`,
            `Condición de pago: ${resumen.condicion}`,
            '', 'Productos:',
            ...resumen.productos.flatMap((p, i) => [`${i + 1}. ${p.descripcion} | ${p.cantidad} ${p.modalidad}(s)${p.modalidad === 'empaque' ? ' · ' + p.empaque : ''} | S/ ${p.importe}`, ...(p.paquetes || []).map(paquete => `  Paquete ${paquete.numero}: ${paquete.estado === 'definida' ? paquete.componentes.map(c => `${c.cantidad} de ${c.descripcion} (${c.sku})`).join('; ') : 'Composición pendiente'}`)]),
            '', `TOTAL: S/ ${resumen.total}`,
        ];
        const texto = lineas.join('\n');
        const estado = document.getElementById('estado-copia-whatsapp');
        const manual = document.getElementById('resumen-whatsapp-manual');
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Portapapeles no disponible');
            await navigator.clipboard.writeText(texto);
            estado.textContent = 'Resumen copiado. Puedes pegarlo en un chat de WhatsApp.';
            manual.hidden = true;
        } catch (_) {
            manual.value = texto;
            manual.hidden = false;
            manual.focus();
            manual.select();
            estado.textContent = 'Tu navegador no permitió copiar automáticamente. Copia el texto seleccionado y pégalo en WhatsApp.';
        }
    });
})();
</script>
@endpush
