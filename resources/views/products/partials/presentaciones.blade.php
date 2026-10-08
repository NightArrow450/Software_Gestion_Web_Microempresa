<section class="form-section">
    <div class="dynamic-row-head">
        <div>
            <h2 class="form-section-title" style="margin-bottom:6px;">Presentaciones comerciales</h2>
            <div class="muted">Registra la capacidad, envase y forma de venta de cada presentación.</div>
        </div>

        <button type="button" class="btn btn-secondary" data-add-presentation>
            <span class="material-symbols-outlined">add</span>
            Agregar presentación
        </button>
    </div>

    <div
        class="dynamic-list"
        data-presentation-list
        data-next-index="{{ count($presentacionesForm) }}"
    >
        @foreach($presentacionesForm as $indice => $presentacion)
            @php
                $vendeUnidad = (string) ($presentacion['venta_por_unidad'] ?? '1') === '1';
                $vendeEmpaque = (string) ($presentacion['venta_por_empaque'] ?? '0') === '1';
                $varianteSeleccionada = (string) ($presentacion['variante_clave'] ?? '');
            @endphp

            <div class="presentation-card" data-presentation-row>
                <div class="presentation-head">
                    <div class="presentation-title" data-presentation-title>Presentación {{ $loop->iteration }}</div>

                    <button type="button" class="icon-button" title="Quitar presentación" data-remove-presentation>
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>

                <input type="hidden" name="presentaciones[{{ $indice }}][id]" value="{{ $presentacion['id'] ?? '' }}">

                <div class="presentation-grid">
                    <div class="form-group">
                        <label class="form-label">Código SKU *</label>
                        <input
                            type="text"
                            name="presentaciones[{{ $indice }}][codigo_sku]"
                            value="{{ $presentacion['codigo_sku'] ?? '' }}"
                            class="form-control"
                            placeholder="Ej. LEJ-ECO-650"
                            maxlength="50"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Variante</label>
                        <select
                            name="presentaciones[{{ $indice }}][variante_clave]"
                            class="form-control"
                            data-variant-select
                            data-selected="{{ $varianteSeleccionada }}"
                        >
                            <option value="">Sin variante</option>
                            @foreach($variantesForm as $variante)
                                <option
                                    value="{{ $variante['clave'] ?? '' }}"
                                    {{ $varianteSeleccionada === (string) ($variante['clave'] ?? '') ? 'selected' : '' }}
                                >
                                    {{ $variante['tipo'] ?? 'Variante' }}: {{ $variante['valor'] ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipo de envase</label>
                        <input
                            type="text"
                            name="presentaciones[{{ $indice }}][tipo_envase]"
                            value="{{ $presentacion['tipo_envase'] ?? '' }}"
                            class="form-control"
                            placeholder="Ej. Botella, galón o bidón"
                            maxlength="60"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contenido *</label>
                        <input
                            type="number"
                            name="presentaciones[{{ $indice }}][contenido]"
                            value="{{ $presentacion['contenido'] ?? '' }}"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Unidad de medida *</label>
                        <select name="presentaciones[{{ $indice }}][unidad_medida]" class="form-control" required>
                            @foreach(['L', 'ml', 'kg', 'g'] as $unidad)
                                <option
                                    value="{{ $unidad }}"
                                    {{ (string) ($presentacion['unidad_medida'] ?? 'ml') === $unidad ? 'selected' : '' }}
                                >
                                    {{ $unidad }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado *</label>
                        <select name="presentaciones[{{ $indice }}][estado]" class="form-control" required>
                            <option value="1" {{ (string) ($presentacion['estado'] ?? '1') === '1' ? 'selected' : '' }}>Activa</option>
                            <option value="0" {{ (string) ($presentacion['estado'] ?? '1') === '0' ? 'selected' : '' }}>Inactiva</option>
                        </select>
                    </div>
                </div>

                <div class="sale-options">
                    <div class="sale-box">
                        <label class="check-row">
                            <input type="hidden" name="presentaciones[{{ $indice }}][venta_por_unidad]" value="0">
                            <input
                                type="checkbox"
                                name="presentaciones[{{ $indice }}][venta_por_unidad]"
                                value="1"
                                {{ $vendeUnidad ? 'checked' : '' }}
                                data-sale-unit
                            >
                            Se vende por unidad
                        </label>

                        <div class="sale-fields" data-unit-fields>
                            <div class="form-group">
                                <label class="form-label">Precio unitario</label>
                                <div class="money-prefix">
                                    <input
                                        type="number"
                                        name="presentaciones[{{ $indice }}][precio_unitario]"
                                        value="{{ $presentacion['precio_unitario'] ?? '' }}"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                    >
                                </div>
                                <span class="form-help">Puede quedar vacío hasta tener el precio real.</span>
                            </div>
                        </div>
                    </div>

                    <div class="sale-box">
                        <label class="check-row">
                            <input type="hidden" name="presentaciones[{{ $indice }}][venta_por_empaque]" value="0">
                            <input
                                type="checkbox"
                                name="presentaciones[{{ $indice }}][venta_por_empaque]"
                                value="1"
                                {{ $vendeEmpaque ? 'checked' : '' }}
                                data-sale-pack
                            >
                            Se vende por empaque
                        </label>

                        <div class="sale-fields" data-pack-fields>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Tipo de empaque</label>
                                    <select name="presentaciones[{{ $indice }}][tipo_empaque]" class="form-control">
                                        <option value="">Seleccione</option>
                                        <option value="Paquete" {{ ($presentacion['tipo_empaque'] ?? '') === 'Paquete' ? 'selected' : '' }}>Paquete</option>
                                        <option value="Caja" {{ ($presentacion['tipo_empaque'] ?? '') === 'Caja' ? 'selected' : '' }}>Caja</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Unidades por empaque</label>
                                    <input
                                        type="number"
                                        name="presentaciones[{{ $indice }}][unidades_por_empaque]"
                                        value="{{ $presentacion['unidades_por_empaque'] ?? '' }}"
                                        class="form-control"
                                        min="1"
                                        step="1"
                                    >
                                </div>
                            </div>

                            <div class="form-group" style="margin-top:14px;">
                                <label class="form-label">Precio por empaque</label>
                                <div class="money-prefix">
                                    <input
                                        type="number"
                                        name="presentaciones[{{ $indice }}][precio_empaque]"
                                        value="{{ $presentacion['precio_empaque'] ?? '' }}"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                    >
                                </div>
                                <span class="form-help">Usa “Caja” solo cuando la empresa realmente comercialice en caja.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

<template id="presentation-template">
    <div class="presentation-card" data-presentation-row>
        <div class="presentation-head">
            <div class="presentation-title" data-presentation-title>Presentación</div>
            <button type="button" class="icon-button" title="Quitar presentación" data-remove-presentation>
                <span class="material-symbols-outlined">delete</span>
            </button>
        </div>

        <input type="hidden" name="presentaciones[__INDEX__][id]" value="">

        <div class="presentation-grid">
            <div class="form-group">
                <label class="form-label">Código SKU *</label>
                <input type="text" name="presentaciones[__INDEX__][codigo_sku]" class="form-control" placeholder="Ej. LEJ-ECO-650" maxlength="50" required>
            </div>

            <div class="form-group">
                <label class="form-label">Variante</label>
                <select name="presentaciones[__INDEX__][variante_clave]" class="form-control" data-variant-select data-selected="">
                    <option value="">Sin variante</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Tipo de envase</label>
                <input type="text" name="presentaciones[__INDEX__][tipo_envase]" class="form-control" placeholder="Ej. Botella, galón o bidón" maxlength="60">
            </div>

            <div class="form-group">
                <label class="form-label">Contenido *</label>
                <input type="number" name="presentaciones[__INDEX__][contenido]" class="form-control" min="0.01" step="0.01" required>
            </div>

            <div class="form-group">
                <label class="form-label">Unidad de medida *</label>
                <select name="presentaciones[__INDEX__][unidad_medida]" class="form-control" required>
                    <option value="L">L</option>
                    <option value="ml" selected>ml</option>
                    <option value="kg">kg</option>
                    <option value="g">g</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Estado *</label>
                <select name="presentaciones[__INDEX__][estado]" class="form-control" required>
                    <option value="1" selected>Activa</option>
                    <option value="0">Inactiva</option>
                </select>
            </div>
        </div>

        <div class="sale-options">
            <div class="sale-box">
                <label class="check-row">
                    <input type="hidden" name="presentaciones[__INDEX__][venta_por_unidad]" value="0">
                    <input type="checkbox" name="presentaciones[__INDEX__][venta_por_unidad]" value="1" checked data-sale-unit>
                    Se vende por unidad
                </label>

                <div class="sale-fields" data-unit-fields>
                    <div class="form-group">
                        <label class="form-label">Precio unitario</label>
                        <div class="money-prefix">
                            <input type="number" name="presentaciones[__INDEX__][precio_unitario]" class="form-control" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <span class="form-help">Puede quedar vacío hasta tener el precio real.</span>
                    </div>
                </div>
            </div>

            <div class="sale-box">
                <label class="check-row">
                    <input type="hidden" name="presentaciones[__INDEX__][venta_por_empaque]" value="0">
                    <input type="checkbox" name="presentaciones[__INDEX__][venta_por_empaque]" value="1" data-sale-pack>
                    Se vende por empaque
                </label>

                <div class="sale-fields" data-pack-fields>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Tipo de empaque</label>
                            <select name="presentaciones[__INDEX__][tipo_empaque]" class="form-control">
                                <option value="">Seleccione</option>
                                <option value="Paquete">Paquete</option>
                                <option value="Caja">Caja</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Unidades por empaque</label>
                            <input type="number" name="presentaciones[__INDEX__][unidades_por_empaque]" class="form-control" min="1" step="1">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:14px;">
                        <label class="form-label">Precio por empaque</label>
                        <div class="money-prefix">
                            <input type="number" name="presentaciones[__INDEX__][precio_empaque]" class="form-control" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
