(() => {
    const form = document.querySelector('[data-product-form]');

    if (!form) return;

    const variantList = form.querySelector('[data-variant-list]');
    const presentationList = form.querySelector('[data-presentation-list]');
    const variantTemplate = document.getElementById('variant-template');
    const presentationTemplate = document.getElementById('presentation-template');
    const addVariantButton = form.querySelector('[data-add-variant]');
    const addPresentationButton = form.querySelector('[data-add-presentation]');

    let variantIndex = Number(variantList?.dataset.nextIndex || 0);
    let presentationIndex = Number(presentationList?.dataset.nextIndex || 0);
    let temporaryKey = Date.now();

    const variantRows = () => [...form.querySelectorAll('[data-variant-row]')];
    const presentationRows = () => [...form.querySelectorAll('[data-presentation-row]')];

    function updateVariantOptions() {
        const variants = variantRows().map((row) => {
            const key = row.querySelector('[data-variant-key]')?.value || '';
            const type = row.querySelector('[data-variant-type]')?.value.trim() || '';
            const value = row.querySelector('[data-variant-value]')?.value.trim() || '';

            return {
                key,
                label: type && value ? `${type}: ${value}` : (value || type || 'Variante sin completar'),
            };
        }).filter((variant) => variant.key);

        form.querySelectorAll('[data-variant-select]').forEach((select) => {
            const selected = select.value || select.dataset.selected || '';
            select.innerHTML = '';

            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'Sin variante';
            select.appendChild(emptyOption);

            variants.forEach((variant) => {
                const option = document.createElement('option');
                option.value = variant.key;
                option.textContent = variant.label;
                select.appendChild(option);
            });

            if ([...select.options].some((option) => option.value === selected)) {
                select.value = selected;
            }

            select.dataset.selected = select.value;
        });
    }

    function updateSaleVisibility(card) {
        const unit = card.querySelector('[data-sale-unit]');
        const pack = card.querySelector('[data-sale-pack]');
        const unitFields = card.querySelector('[data-unit-fields]');
        const packFields = card.querySelector('[data-pack-fields]');

        if (unitFields) {
            unitFields.style.display = unit?.checked ? 'block' : 'none';
        }

        if (packFields) {
            packFields.style.display = pack?.checked ? 'block' : 'none';
        }
    }

    function refreshPresentationTitles() {
        presentationRows().forEach((row, index) => {
            const title = row.querySelector('[data-presentation-title]');
            if (title) title.textContent = `Presentación ${index + 1}`;
        });
    }

    addVariantButton?.addEventListener('click', () => {
        if (!variantTemplate || !variantList) return;

        const key = `tmp_${temporaryKey++}`;
        const html = variantTemplate.innerHTML
            .replaceAll('__INDEX__', variantIndex++)
            .replaceAll('__CLAVE__', key);

        variantList.insertAdjacentHTML('beforeend', html);
        updateVariantOptions();
    });

    addPresentationButton?.addEventListener('click', () => {
        if (!presentationTemplate || !presentationList) return;

        const html = presentationTemplate.innerHTML
            .replaceAll('__INDEX__', presentationIndex++);

        presentationList.insertAdjacentHTML('beforeend', html);
        updateVariantOptions();

        const row = presentationRows().at(-1);
        if (row) updateSaleVisibility(row);

        refreshPresentationTitles();
    });

    form.addEventListener('click', (event) => {
        const removeVariant = event.target.closest('[data-remove-variant]');
        const removePresentation = event.target.closest('[data-remove-presentation]');

        if (removeVariant) {
            removeVariant.closest('[data-variant-row]')?.remove();
            updateVariantOptions();
        }

        if (removePresentation) {
            removePresentation.closest('[data-presentation-row]')?.remove();
            refreshPresentationTitles();
        }
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-variant-type], [data-variant-value]')) {
            updateVariantOptions();
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-variant-select]')) {
            event.target.dataset.selected = event.target.value;
        }

        if (event.target.matches('[data-sale-unit], [data-sale-pack]')) {
            const row = event.target.closest('[data-presentation-row]');
            if (row) updateSaleVisibility(row);
        }
    });

    const imageInput = form.querySelector('[data-image-input]');
    const imagePreview = form.querySelector('[data-image-preview]');
    const imagePlaceholder = form.querySelector('[data-image-placeholder]');
    const removeImageButton = form.querySelector('[data-remove-image]');
    const removeImageFlag = form.querySelector('[data-remove-image-flag]');

    imageInput?.addEventListener('change', () => {
        const file = imageInput.files?.[0];
        if (!file) return;

        if (imagePreview) {
            imagePreview.src = URL.createObjectURL(file);
            imagePreview.style.display = 'block';
        }

        if (imagePlaceholder) imagePlaceholder.style.display = 'none';
        if (removeImageButton) removeImageButton.style.display = 'inline-flex';
        if (removeImageFlag) removeImageFlag.value = '0';
    });

    removeImageButton?.addEventListener('click', () => {
        if (imageInput) imageInput.value = '';
        if (imagePreview) {
            imagePreview.removeAttribute('src');
            imagePreview.style.display = 'none';
        }
        if (imagePlaceholder) imagePlaceholder.style.display = 'grid';
        if (removeImageButton) removeImageButton.style.display = 'none';
        if (removeImageFlag) removeImageFlag.value = '1';
    });

    presentationRows().forEach(updateSaleVisibility);
    refreshPresentationTitles();
    updateVariantOptions();
})();
