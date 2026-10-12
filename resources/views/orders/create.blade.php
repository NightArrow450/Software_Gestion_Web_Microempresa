@extends('layouts.app')

@section('title', 'Nuevo Pedido')
@section('page-title', 'Nuevo Pedido')
@section('breadcrumb', 'Inicio / Pedidos / Nuevo')

@push('styles')
<style>
    .linea-pedido {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 12px;
        border: 1px solid #e4e9e6;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        background: #fff;
    }

    .linea-pedido .form-group {
        margin: 0;
    }

    .pedido-resumen {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px solid #e4e9e6;
        padding-top: 18px;
        margin-top: 18px;
        font-size: 20px;
    }

    .pedido-errores {
        padding: 14px 18px;
        background: #fff3f2;
        color: #9a2119;
        border: 1px solid #f4c7c3;
        border-radius: 10px;
        margin-bottom: 22px;
    }
</style>
@endpush

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">Operaciones comerciales</div>
        <h1>Registrar pedido</h1>

        <p>
            Selecciona un cliente activo y las presentaciones solicitadas.
        </p>
    </div>

    <a
        href="{{ route('pedidos.index') }}"
        class="btn btn-secondary"
    >
        Volver
    </a>
</div>

@if($errors->any())
    <div class="pedido-errores">
        <strong>
            Revisa los datos del pedido:
        </strong>

        <ul style="padding-left:20px; margin-top:6px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form
    method="POST"
    action="{{ route('pedidos.store') }}"
    id="form-pedido"
>
    @csrf

    <section class="form-section">
        <h2 class="form-section-title">
            Datos del pedido
        </h2>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="cliente_id">
                    Cliente *
                </label>

                <select
                    name="cliente_id"
                    id="cliente_id"
                    class="form-control"
                    required
                >
                    <option value="">
                        Seleccionar cliente
                    </option>

                    @foreach($clientes as $cliente)
                        <option
                            value="{{ $cliente->id }}"
                            @selected((string) old('cliente_id') === (string) $cliente->id)
                        >
                            {{ $cliente->nombre }}

                            @if($cliente->mercado)
                                — {{ $cliente->mercado }}
                            @endif
                        </option>
                    @endforeach
                </select>

                @if($clientes->isEmpty())
                    <div class="muted">
                        Antes debes registrar al menos un cliente activo.
                    </div>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label" for="condicion_pago">
                    Condición de pago *
                </label>

                <select
                    name="condicion_pago"
                    id="condicion_pago"
                    class="form-control"
                    required
                >
                    <option
                        value="contado"
                        @selected(old('condicion_pago', 'contado') === 'contado')
                    >
                        Al contado
                    </option>

                    <option
                        value="credito"
                        @selected(old('condicion_pago') === 'credito')
                    >
                        Crédito
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="fecha_pedido">
                    Fecha del pedido *
                </label>

                <input
                    type="date"
                    class="form-control"
                    name="fecha_pedido"
                    id="fecha_pedido"
                    value="{{ old('fecha_pedido', now()->toDateString()) }}"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="fecha_entrega">
                    Fecha de entrega *
                </label>

                <input
                    type="date"
                    class="form-control"
                    name="fecha_entrega"
                    id="fecha_entrega"
                    value="{{ old('fecha_entrega', now()->toDateString()) }}"
                    required
                >
            </div>
        </div>

        <div class="form-group" style="margin-top:16px;">
            <label class="form-label" for="observaciones">
                Observaciones (opcional)
            </label>

            <textarea
                class="form-control"
                name="observaciones"
                id="observaciones"
                rows="3"
                maxlength="1000"
            >{{ old('observaciones') }}</textarea>
        </div>
    </section>

    <section class="form-section" style="margin-top:22px;">
        <h2 class="form-section-title">
            Productos solicitados
        </h2>

        <div class="muted" style="margin-bottom:15px;">
            Las cajas se calculan automáticamente según el precio
            unitario y las unidades por empaque.
        </div>

        <div id="lineas-pedido">
            @foreach(
                old('detalles', [
                    [
                        'presentacion_producto_id' => '',
                        'modalidad' => 'unidad',
                        'cantidad' => 1
                    ]
                ]) as $indice => $fila
            )
                @include('orders.partials.linea', [
                    'indice' => $indice,
                    'fila' => $fila
                ])
            @endforeach
        </div>

        <button
            type="button"
            id="agregar-linea"
            class="btn btn-secondary"
        >
            <span class="material-symbols-outlined">
                add
            </span>
            Agregar producto
        </button>

        <div class="pedido-resumen">
            <span>
                Total calculado
            </span>

            <strong id="total-pedido">
                S/ 0.00
            </strong>
        </div>
    </section>

    <div class="form-actions" style="margin-top:20px;">
        <a
            href="{{ route('pedidos.index') }}"
            class="btn btn-secondary"
        >
            Cancelar
        </a>

        <button
            type="submit"
            class="btn btn-primary"
            @disabled($clientes->isEmpty() || $presentaciones->isEmpty())
        >
            <span class="material-symbols-outlined">
                save
            </span>
            Registrar pedido
        </button>
    </div>
</form>

<template id="plantilla-linea">
    @include('orders.partials.linea', [
        'indice' => '__indice__',
        'fila' => []
    ])
</template>
@endsection

@push('scripts')
<script>
(() => {
    const contenedor = document.querySelector('#lineas-pedido');
    const plantilla = document.querySelector('#plantilla-linea');
    const botonAgregar = document.querySelector('#agregar-linea');
    const totalVisual = document.querySelector('#total-pedido');

    let siguiente = {{ count(old('detalles', [1])) }};

    const moneda = centimos =>
        'S/ ' + (centimos / 100).toFixed(2);

    function recalcular() {
        let total = 0;

        contenedor.querySelectorAll('[data-linea]').forEach(fila => {
            const selector = fila.querySelector('[data-presentacion]');
            const modalidad = fila.querySelector('[data-modalidad]');
            const opcion = selector.selectedOptions[0];

            const unidad =
                opcion && opcion.dataset.unidad === '1';

            const empaque =
                opcion &&
                opcion.dataset.empaque === '1' &&
                Number(opcion.dataset.unidades) > 0;

            modalidad.querySelector(
                'option[value="unidad"]'
            ).disabled = !unidad;

            modalidad.querySelector(
                'option[value="empaque"]'
            ).disabled = !empaque;

            if (
                (modalidad.value === 'unidad' && !unidad) ||
                (modalidad.value === 'empaque' && !empaque)
            ) {
                modalidad.value = unidad
                    ? 'unidad'
                    : (empaque ? 'empaque' : 'unidad');
            }

            const base = Math.round(
                Number(opcion?.dataset.precio ?? 0) * 100
            );

            const precio = modalidad.value === 'empaque'
                ? base * Number(opcion?.dataset.unidades ?? 0)
                : base;

            const cantidad = Math.max(
                0,
                Number(
                    fila.querySelector('[data-cantidad]').value || 0
                )
            );

            const importe = precio * cantidad;

            fila.querySelector(
                '[data-precio-visual]'
            ).textContent = selector.value
                ? moneda(precio)
                : '—';

            fila.querySelector(
                '[data-importe-visual]'
            ).textContent = moneda(importe);

            total += importe;
        });

        totalVisual.textContent = moneda(total);

        botonAgregar.disabled =
            contenedor.querySelectorAll('[data-linea]').length >= 18;
    }

    botonAgregar.addEventListener('click', () => {
        if (
            contenedor.querySelectorAll('[data-linea]').length >= 18
        ) {
            return;
        }

        contenedor.insertAdjacentHTML(
            'beforeend',
            plantilla.innerHTML.replaceAll(
                '__indice__',
                String(siguiente++)
            )
        );

        recalcular();
    });

    contenedor.addEventListener('input', recalcular);
    contenedor.addEventListener('change', recalcular);

    contenedor.addEventListener('click', event => {
        const quitar = event.target.closest('[data-quitar]');

        if (!quitar) {
            return;
        }

        const filas = contenedor.querySelectorAll('[data-linea]');

        if (filas.length > 1) {
            quitar.closest('[data-linea]').remove();
        } else {
            const fila = filas[0];

            fila.querySelector(
                '[data-presentacion]'
            ).value = '';

            fila.querySelector(
                '[data-cantidad]'
            ).value = '1';
        }

        recalcular();
    });

    recalcular();
})();
</script>
@endpush
