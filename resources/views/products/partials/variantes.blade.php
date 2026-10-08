<section class="form-section">
    <div class="dynamic-row-head">
        <div>
            <h2 class="form-section-title" style="margin-bottom:6px;">Variantes</h2>
            <div class="muted">Regístralas solo si el producto cambia por aroma, color u otra característica.</div>
        </div>

        <button type="button" class="btn btn-secondary" data-add-variant>
            <span class="material-symbols-outlined">add</span>
            Agregar variante
        </button>
    </div>

    <div
        class="dynamic-list"
        data-variant-list
        data-next-index="{{ count($variantesForm) }}"
    >
        @foreach($variantesForm as $indice => $variante)
            <div class="dynamic-row" data-variant-row>
                <div class="dynamic-row-head">
                    <strong>Variante</strong>
                    <button type="button" class="icon-button" title="Quitar variante" data-remove-variant>
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>

                <input type="hidden" name="variantes[{{ $indice }}][id]" value="{{ $variante['id'] ?? '' }}">
                <input
                    type="hidden"
                    name="variantes[{{ $indice }}][clave]"
                    value="{{ $variante['clave'] ?? 'tmp_'.$indice }}"
                    data-variant-key
                >

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Tipo *</label>
                        <input
                            type="text"
                            name="variantes[{{ $indice }}][tipo]"
                            value="{{ $variante['tipo'] ?? '' }}"
                            class="form-control"
                            placeholder="Ej. Aroma o Color"
                            maxlength="50"
                            data-variant-type
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Valor *</label>
                        <input
                            type="text"
                            name="variantes[{{ $indice }}][valor]"
                            value="{{ $variante['valor'] ?? '' }}"
                            class="form-control"
                            placeholder="Ej. Lavanda o Rosado"
                            maxlength="100"
                            data-variant-value
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado *</label>
                        <select name="variantes[{{ $indice }}][estado]" class="form-control" required>
                            <option value="1" {{ (string) ($variante['estado'] ?? '1') === '1' ? 'selected' : '' }}>Activa</option>
                            <option value="0" {{ (string) ($variante['estado'] ?? '1') === '0' ? 'selected' : '' }}>Inactiva</option>
                        </select>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if(empty($variantesForm))
        <div class="form-help" style="margin-top:12px;">
            Puedes dejar esta sección vacía si el producto no tiene variantes.
        </div>
    @endif
</section>

<template id="variant-template">
    <div class="dynamic-row" data-variant-row>
        <div class="dynamic-row-head">
            <strong>Variante</strong>
            <button type="button" class="icon-button" title="Quitar variante" data-remove-variant>
                <span class="material-symbols-outlined">delete</span>
            </button>
        </div>

        <input type="hidden" name="variantes[__INDEX__][id]" value="">
        <input type="hidden" name="variantes[__INDEX__][clave]" value="__CLAVE__" data-variant-key>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Tipo *</label>
                <input
                    type="text"
                    name="variantes[__INDEX__][tipo]"
                    class="form-control"
                    placeholder="Ej. Aroma o Color"
                    maxlength="50"
                    data-variant-type
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label">Valor *</label>
                <input
                    type="text"
                    name="variantes[__INDEX__][valor]"
                    class="form-control"
                    placeholder="Ej. Lavanda o Rosado"
                    maxlength="100"
                    data-variant-value
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label">Estado *</label>
                <select name="variantes[__INDEX__][estado]" class="form-control" required>
                    <option value="1" selected>Activa</option>
                    <option value="0">Inactiva</option>
                </select>
            </div>
        </div>
    </div>
</template>
