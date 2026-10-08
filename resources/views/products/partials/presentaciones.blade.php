<section class="form-section product-collapsible-section" data-section="presentations">
    <div class="dynamic-row-head">
        <div>
            <h2 class="form-section-title" style="margin-bottom:6px;">Presentaciones comerciales</h2>
            <div class="muted">
                Registra una presentación una sola vez y aplícala a todas las variantes que correspondan.
            </div>
        </div>

        <div class="section-inline-actions">
            <button type="button" class="btn-link" data-collapse-all="presentations">Contraer todo</button>
            <button type="button" class="btn btn-secondary" data-add-presentation>
                <span class="material-symbols-outlined">add</span>
                Agregar presentación
            </button>
        </div>
    </div>

    <div class="sku-prefix-box">
        <div class="form-group" style="margin:0; max-width:360px;">
            <label for="prefijo_sku" class="form-label">Prefijo SKU *</label>
            <input
                type="text"
                id="prefijo_sku"
                name="prefijo_sku"
                value="{{ old('prefijo_sku', '') }}"
                class="form-control"
                maxlength="30"
                placeholder="Ej. JTA-FLA"
                autocomplete="off"
                data-sku-prefix
                required
            >
            <span class="form-help">
                Se genera a partir del nombre y la marca. Puedes corregir el prefijo antes de guardar.
            </span>
        </div>

        <div class="sku-prefix-example">
            <strong>Ejemplo</strong>
            <span>JTA-FLA + Lavanda + 4 L → JTA-FLA-LAV-4L</span>
        </div>
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
                $variantesSeleccionadas = array_map('strval', $presentacion['variantes_clave'] ?? []);
                $idsExistentes = $presentacion['ids'] ?? [];
            @endphp

            <div class="presentation-card" data-presentation-row data-presentation-index="{{ $indice }}">
                <div class="presentation-head">
                    <div>
                        <div class="presentation-title" data-presentation-title>Presentación {{ $loop->iteration }}</div>
                        <div class="muted" style="font-size:12px; margin-top:3px;" data-presentation-summary>
                            El SKU se genera automáticamente.
                        </div>
                    </div>

                    <div class="card-head-actions">
                        <button type="button" class="icon-button" title="Minimizar" data-collapse-toggle>
                            <span class="material-symbols-outlined" data-collapse-icon>expand_less</span>
                        </button>
                        <button type="button" class="icon-button" title="Quitar presentación" data-remove-presentation>
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                </div>

                <div data-collapsible-body>
                @foreach($idsExistentes as $claveRelacion => $idPresentacion)
                    <input
                        type="hidden"
                        name="presentaciones[{{ $indice }}][ids][{{ $claveRelacion }}]"
                        value="{{ $idPresentacion }}"
                    >
                @endforeach

                <div class="presentation-grid">
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
                            data-presentation-content
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Unidad de medida *</label>
                        <select
                            name="presentaciones[{{ $indice }}][unidad_medida]"
                            class="form-control"
                            data-presentation-unit
                            required
                        >
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
                        <select
                            name="presentaciones[{{ $indice }}][estado]"
                            class="form-control"
                            data-child-status
                            data-child-type="presentacion"
                            required
                        >
                            <option value="1" {{ (string) ($presentacion['estado'] ?? '1') === '1' ? 'selected' : '' }}>Activa</option>
                            <option value="0" {{ (string) ($presentacion['estado'] ?? '1') === '0' ? 'selected' : '' }}>Inactiva</option>
                        </select>
                    </div>
                </div>

                <div class="variant-apply-box">
                    <div class="variant-apply-head">
                        <div>
                            <strong>Aplicar a variantes</strong>
                            <div class="muted" style="font-size:12px; margin-top:3px;">
                                Selecciona varias para crear todos los SKU en una sola operación. Si no seleccionas ninguna, se crea una presentación sin variante.
                            </div>
                        </div>

                        <div class="variant-apply-actions">
                            <button type="button" class="btn-link" data-select-all-variants>Todas</button>
                            <button type="button" class="btn-link" data-clear-variants>Ninguna</button>
                        </div>
                    </div>

                    <div class="variant-check-grid" data-variant-options>
                        @forelse($variantesForm as $variante)
                            @php
                                $claveVariante = (string) ($variante['clave'] ?? '');
                            @endphp

                            <label class="variant-check-item">
                                <input
                                    type="checkbox"
                                    name="presentaciones[{{ $indice }}][variantes_clave][]"
                                    value="{{ $claveVariante }}"
                                    {{ in_array($claveVariante, $variantesSeleccionadas, true) ? 'checked' : '' }}
                                    data-presentation-variant
                                >
                                <span>
                                    <strong>{{ $variante['valor'] ?? 'Variante' }}</strong>
                                    <small>{{ $variante['tipo'] ?? '' }}</small>
                                </span>
                            </label>
                        @empty
                            <div class="muted" data-no-variants>
                                Este producto todavía no tiene variantes registradas.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="sku-preview-box">
                    <div class="sku-preview-title">
                        <span class="material-symbols-outlined">qr_code_2</span>
                        SKU que se generarán
                    </div>
                    <div class="sku-preview-list" data-sku-preview>
                        <span class="muted">Completa el contenido para ver la vista previa.</span>
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
            </div>
        @endforeach
    </div>
</section>

<template id="presentation-template">
    <div class="presentation-card" data-presentation-row data-presentation-index="__INDEX__">
        <div class="presentation-head">
            <div>
                <div class="presentation-title" data-presentation-title>Presentación</div>
                <div class="muted" style="font-size:12px; margin-top:3px;" data-presentation-summary>El SKU se genera automáticamente.</div>
            </div>

            <div class="card-head-actions">
                <button type="button" class="icon-button" title="Minimizar" data-collapse-toggle>
                    <span class="material-symbols-outlined" data-collapse-icon>expand_less</span>
                </button>
                <button type="button" class="icon-button" title="Quitar presentación" data-remove-presentation>
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
        </div>

        <div data-collapsible-body>
        <div class="presentation-grid">
            <div class="form-group">
                <label class="form-label">Tipo de envase</label>
                <input type="text" name="presentaciones[__INDEX__][tipo_envase]" class="form-control" placeholder="Ej. Botella, galón o bidón" maxlength="60">
            </div>

            <div class="form-group">
                <label class="form-label">Contenido *</label>
                <input type="number" name="presentaciones[__INDEX__][contenido]" class="form-control" min="0.01" step="0.01" data-presentation-content required>
            </div>

            <div class="form-group">
                <label class="form-label">Unidad de medida *</label>
                <select name="presentaciones[__INDEX__][unidad_medida]" class="form-control" data-presentation-unit required>
                    <option value="L">L</option>
                    <option value="ml" selected>ml</option>
                    <option value="kg">kg</option>
                    <option value="g">g</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Estado *</label>
                <select
                    name="presentaciones[__INDEX__][estado]"
                    class="form-control"
                    data-child-status
                    data-child-type="presentacion"
                    required
                >
                    <option value="1" selected>Activa</option>
                    <option value="0">Inactiva</option>
                </select>
            </div>
        </div>

        <div class="variant-apply-box">
            <div class="variant-apply-head">
                <div>
                    <strong>Aplicar a variantes</strong>
                    <div class="muted" style="font-size:12px; margin-top:3px;">
                        Selecciona varias para crear todos los SKU de esta presentación.
                    </div>
                </div>

                <div class="variant-apply-actions">
                    <button type="button" class="btn-link" data-select-all-variants>Todas</button>
                    <button type="button" class="btn-link" data-clear-variants>Ninguna</button>
                </div>
            </div>

            <div class="variant-check-grid" data-variant-options>
                <div class="muted" data-no-variants>Este producto todavía no tiene variantes registradas.</div>
            </div>
        </div>

        <div class="sku-preview-box">
            <div class="sku-preview-title">
                <span class="material-symbols-outlined">qr_code_2</span>
                SKU que se generarán
            </div>
            <div class="sku-preview-list" data-sku-preview>
                <span class="muted">Completa el contenido para ver la vista previa.</span>
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
    </div>
</template>
