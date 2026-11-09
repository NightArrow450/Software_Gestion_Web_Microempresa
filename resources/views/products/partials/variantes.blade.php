@php
    $gruposVariantesForm = collect($variantesForm)
        ->groupBy(function ($variante) {
            $tipo = trim((string) ($variante['tipo'] ?? ''));
            return $tipo !== '' ? mb_strtolower($tipo) : '__sin_tipo__';
        })
        ->values()
        ->map(function ($items) {
            $primera = $items->first();

            return [
                'tipo' => $primera['tipo'] ?? '',
                'valores' => $items->values()->all(),
            ];
        })
        ->all();

    $indiceVariantePlano = 0;
@endphp

<section class="form-section product-collapsible-section" data-section="variants">
    <div class="dynamic-row-head">
        <div>
            <h2 class="form-section-title" style="margin-bottom:6px;">Variantes</h2>
            <div class="muted">
                Agrupa los valores por tipo. Por ejemplo: Aroma con 6 nombres dentro del mismo bloque.
            </div>
        </div>

        <div class="section-inline-actions">
            <button type="button" class="btn-link" data-collapse-all="variants">Contraer todo</button>
            <button type="button" class="btn btn-secondary" data-add-variant>
                <span class="material-symbols-outlined">add</span>
                Agregar tipo de variante
            </button>
        </div>
    </div>

    <div
        class="dynamic-list"
        data-variant-list
        data-next-index="{{ count($variantesForm) }}"
    >
        @foreach($gruposVariantesForm as $grupo)
            <div class="variant-group-card collapsible-card" data-variant-group>
                <div class="variant-group-head">
                    <div>
                        <strong data-variant-group-title>
                            {{ $grupo['tipo'] ?: 'Nuevo tipo de variante' }}
                        </strong>
                        <div class="muted variant-group-summary" data-variant-group-summary>
                            {{ count($grupo['valores']) }} valor(es)
                        </div>
                    </div>

                    <div class="card-head-actions">
                        <button type="button" class="icon-button" title="Minimizar" data-collapse-toggle>
                            <span class="material-symbols-outlined" data-collapse-icon>expand_less</span>
                        </button>

                        <button type="button" class="icon-button" title="Quitar tipo de variante" data-remove-variant-group>
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                </div>

                <div data-collapsible-body>
                    <div class="variant-group-config">
                        <div class="form-group">
                            <label class="form-label">Tipo de variante *</label>
                            <input
                                type="text"
                                value="{{ $grupo['tipo'] }}"
                                class="form-control"
                                placeholder="Ej. Aroma o Color"
                                maxlength="50"
                                data-variant-group-type
                                required
                            >
                        </div>

                        <div class="form-group variant-count-field">
                            <label class="form-label">Cantidad de valores *</label>
                            <input
                                type="number"
                                value="{{ max(1, count($grupo['valores'])) }}"
                                class="form-control"
                                min="1"
                                max="50"
                                step="1"
                                data-variant-count
                                required
                            >
                            <span class="form-help">Ejemplo: 6 aromas.</span>
                        </div>
                    </div>

                    <div class="variant-values-head">
                        <strong>Nombres de las variantes</strong>
                        <button type="button" class="btn-link" data-add-variant-value>+ Agregar valor</button>
                    </div>

                    <div class="variant-values-list" data-variant-values>
                        @foreach($grupo['valores'] as $variante)
                            <div class="variant-value-row" data-variant-row>
                                <span class="variant-value-number" data-variant-number>{{ $loop->iteration }}</span>

                                <input type="hidden" name="variantes[{{ $indiceVariantePlano }}][id]" value="{{ $variante['id'] ?? '' }}">
                                <input
                                    type="hidden"
                                    name="variantes[{{ $indiceVariantePlano }}][clave]"
                                    value="{{ $variante['clave'] ?? 'tmp_'.$indiceVariantePlano }}"
                                    data-variant-key
                                >
                                <input
                                    type="hidden"
                                    name="variantes[{{ $indiceVariantePlano }}][tipo]"
                                    value="{{ $variante['tipo'] ?? $grupo['tipo'] }}"
                                    data-variant-type
                                >

                                <div class="form-group variant-value-name">
                                    <label class="form-label">Valor *</label>
                                    <input
                                        type="text"
                                        name="variantes[{{ $indiceVariantePlano }}][valor]"
                                        value="{{ $variante['valor'] ?? '' }}"
                                        class="form-control"
                                        placeholder="Ej. Lavanda"
                                        maxlength="100"
                                        data-variant-value
                                        required
                                    >
                                </div>

                                <div class="form-group variant-value-status">
                                    <label class="form-label">Estado *</label>
                                    <select
                                        name="variantes[{{ $indiceVariantePlano }}][estado]"
                                        class="form-control"
                                        data-child-status
                                        data-child-type="variante"
                                        required
                                    >
                                        <option value="1" {{ (string) ($variante['estado'] ?? '1') === '1' ? 'selected' : '' }}>Activa</option>
                                        <option value="0" {{ (string) ($variante['estado'] ?? '1') === '0' ? 'selected' : '' }}>Inactiva</option>
                                    </select>
                                </div>

                                <button type="button" class="icon-button variant-value-remove" title="Quitar valor" data-remove-variant>
                                    <span class="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            @php($indiceVariantePlano++)
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if(empty($gruposVariantesForm))
        <div class="form-help" style="margin-top:12px;" data-no-variant-groups>
            Puedes dejar esta sección vacía si el producto no tiene variantes.
        </div>
    @endif
</section>

<template id="variant-group-template">
    <div class="variant-group-card collapsible-card" data-variant-group>
        <div class="variant-group-head">
            <div>
                <strong data-variant-group-title>Nuevo tipo de variante</strong>
                <div class="muted variant-group-summary" data-variant-group-summary>1 valor</div>
            </div>

            <div class="card-head-actions">
                <button type="button" class="icon-button" title="Minimizar" data-collapse-toggle>
                    <span class="material-symbols-outlined" data-collapse-icon>expand_less</span>
                </button>
                <button type="button" class="icon-button" title="Quitar tipo de variante" data-remove-variant-group>
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
        </div>

        <div data-collapsible-body>
            <div class="variant-group-config">
                <div class="form-group">
                    <label class="form-label">Tipo de variante *</label>
                    <input
                        type="text"
                        class="form-control"
                        placeholder="Ej. Aroma o Color"
                        maxlength="50"
                        data-variant-group-type
                        required
                    >
                </div>

                <div class="form-group variant-count-field">
                    <label class="form-label">Cantidad de valores *</label>
                    <input type="number" value="1" class="form-control" min="1" max="50" step="1" data-variant-count required>
                    <span class="form-help">Ejemplo: 6 aromas.</span>
                </div>
            </div>

            <div class="variant-values-head">
                <strong>Nombres de las variantes</strong>
                <button type="button" class="btn-link" data-add-variant-value>+ Agregar valor</button>
            </div>

            <div class="variant-values-list" data-variant-values></div>
        </div>
    </div>
</template>

<template id="variant-value-template">
    <div class="variant-value-row" data-variant-row>
        <span class="variant-value-number" data-variant-number>1</span>

        <input type="hidden" name="variantes[__INDEX__][id]" value="">
        <input type="hidden" name="variantes[__INDEX__][clave]" value="__CLAVE__" data-variant-key>
        <input type="hidden" name="variantes[__INDEX__][tipo]" value="" data-variant-type>

        <div class="form-group variant-value-name">
            <label class="form-label">Valor *</label>
            <input
                type="text"
                name="variantes[__INDEX__][valor]"
                class="form-control"
                placeholder="Ej. Lavanda"
                maxlength="100"
                data-variant-value
                required
            >
        </div>

        <div class="form-group variant-value-status">
            <label class="form-label">Estado *</label>
            <select
                name="variantes[__INDEX__][estado]"
                class="form-control"
                data-child-status
                data-child-type="variante"
                required
            >
                <option value="1" selected>Activa</option>
                <option value="0">Inactiva</option>
            </select>
        </div>

        <button type="button" class="icon-button variant-value-remove" title="Quitar valor" data-remove-variant>
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>
</template>
