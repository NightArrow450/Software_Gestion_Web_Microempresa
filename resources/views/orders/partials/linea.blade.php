<div class="linea-pedido" data-linea>

    <div class="form-group" style="flex:3; min-width:230px;">
        <label class="form-label">
            Producto / presentación
        </label>

        <select
            class="form-control"
            name="detalles[{{ $indice }}][presentacion_producto_id]"
            data-presentacion
            required
        >
            <option value="">
                Seleccionar presentación
            </option>

            @foreach($presentaciones as $presentacion)
                <option
                    value="{{ $presentacion->id }}"
                    data-precio="{{ $presentacion->precio_unitario }}"
                    data-unidades="{{ $presentacion->unidades_por_empaque ?? 0 }}"
                    data-unidad="{{ (int) $presentacion->venta_por_unidad }}"
                    data-empaque="{{ (int) $presentacion->venta_por_empaque }}"
                    @selected((string) ($fila['presentacion_producto_id'] ?? '') === (string) $presentacion->id)
                >
                    {{ $presentacion->producto->nombre }}

                    @if($presentacion->variante)
                        - {{ $presentacion->variante->valor }}
                    @endif

                    - {{ $presentacion->contenido }}
                    {{ $presentacion->unidad_medida }}

                    ({{ $presentacion->codigo_sku }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group" style="flex:1; min-width:125px;">
        <label class="form-label">
            Venta por
        </label>

        <select
            class="form-control"
            data-modalidad
            name="detalles[{{ $indice }}][modalidad]"
            required
        >
            <option
                value="unidad"
                @selected(($fila['modalidad'] ?? 'unidad') === 'unidad')
            >
                Unidad
            </option>

            <option
                value="empaque"
                @selected(($fila['modalidad'] ?? '') === 'empaque')
            >
                Caja / empaque
            </option>
        </select>
    </div>

    <div class="form-group" style="flex:1; min-width:100px;">
        <label class="form-label">
            Cantidad
        </label>

        <input
            type="number"
            min="1"
            max="10000"
            step="1"
            required
            class="form-control"
            data-cantidad
            name="detalles[{{ $indice }}][cantidad]"
            value="{{ $fila['cantidad'] ?? 1 }}"
        >
    </div>

    <div style="min-width:145px; padding-top:25px;">
        <div class="muted">
            P.U.:
            <span data-precio-visual>—</span>
        </div>

        <strong data-importe-visual>
            S/ 0.00
        </strong>
    </div>

    <div style="padding-top:24px;">
        <button
            type="button"
            class="icon-button"
            data-quitar
            title="Quitar fila"
        >
            <span class="material-symbols-outlined">
                delete
            </span>
        </button>
    </div>

</div>
