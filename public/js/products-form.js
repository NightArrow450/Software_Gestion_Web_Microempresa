(() => {
    const form = document.querySelector('[data-product-form]');

    if (!form) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Elementos principales
    |--------------------------------------------------------------------------
    */

    const variantList = form.querySelector(
        '[data-variant-list]'
    );

    const presentationList = form.querySelector(
        '[data-presentation-list]'
    );

    const variantGroupTemplate = document.getElementById(
        'variant-group-template'
    );

    const variantValueTemplate = document.getElementById(
        'variant-value-template'
    );

    const presentationTemplate = document.getElementById(
        'presentation-template'
    );

    const addVariantButton = form.querySelector(
        '[data-add-variant]'
    );

    const addPresentationButton = form.querySelector(
        '[data-add-presentation]'
    );

    const prefixInput = form.querySelector(
        '[data-sku-prefix]'
    );

    const productNameInput = form.querySelector(
        '#nombre'
    );

    const brandInput = form.querySelector(
        '#marca'
    );

    const productStatusInput = form.querySelector(
        '[data-product-status]'
    );


    /*
    |--------------------------------------------------------------------------
    | Índices dinámicos
    |--------------------------------------------------------------------------
    */

    let variantIndex = Number(
        variantList?.dataset.nextIndex || 0
    );

    let presentationIndex = Number(
        presentationList?.dataset.nextIndex || 0
    );

    let temporaryKey = Date.now();


    /*
    |--------------------------------------------------------------------------
    | Helpers de elementos
    |--------------------------------------------------------------------------
    */

    const variantGroups = () => [
        ...form.querySelectorAll(
            '[data-variant-group]'
        )
    ];

    const variantRows = () => [
        ...form.querySelectorAll(
            '[data-variant-row]'
        )
    ];

    const presentationRows = () => [
        ...form.querySelectorAll(
            '[data-presentation-row]'
        )
    ];

    const childStatusInputs = () => [
        ...form.querySelectorAll(
            '[data-child-status]'
        )
    ];


    /*
    |--------------------------------------------------------------------------
    | Normalización
    |--------------------------------------------------------------------------
    */

    const normalizeText = (value = '') => value
        .normalize('NFD')
        .replace(
            /[\u0300-\u036f]/g,
            ''
        )
        .toUpperCase()
        .replace(
            /[^A-Z0-9\s-]/g,
            ' '
        )
        .replace(
            /\s+/g,
            ' '
        )
        .trim();


    const cleanSkuPart = (value = '') => normalizeText(value)
        .replace(
            /\s+/g,
            '-'
        )
        .replace(
            /-+/g,
            '-'
        )
        .replace(
            /^-|-$/g,
            ''
        );


    /*
    |--------------------------------------------------------------------------
    | Código abreviado del producto
    |--------------------------------------------------------------------------
    */

    function productCode(value) {

        const stopWords = new Set([
            'DE',
            'DEL',
            'LA',
            'LAS',
            'EL',
            'LOS',
            'Y',
            'CON',
            'PARA',
        ]);


        const words = normalizeText(value)
            .split(' ')
            .filter(
                (word) =>
                    word
                    &&
                    !stopWords.has(word)
            );


        if (!words.length) {
            return '';
        }


        /*
        | Si tiene 3 o más palabras relevantes:
        |
        | Jabón Tocador Antibacterial
        | JTA
        */

        if (words.length >= 3) {

            return words
                .slice(0, 3)
                .map(
                    (word) => word[0]
                )
                .join('');

        }


        /*
        | Si es una palabra:
        |
        | Lejía
        | LEJ
        */

        return words[0].slice(
            0,
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Código abreviado de marca
    |--------------------------------------------------------------------------
    */

    function brandCode(value) {

        const words = normalizeText(value)
            .split(' ')
            .filter(Boolean);


        if (!words.length) {
            return '';
        }


        /*
        | Eco -> ECO
        */

        if (words[0].length <= 3) {

            return words[0].slice(
                0,
                3
            );

        }


        /*
        | Mister Car -> MCA
        */

        if (words.length >= 2) {

            return (
                words[0][0]
                +
                words[1].slice(0, 2)
            );

        }


        /*
        | Flash -> FLA
        */

        return words[0].slice(
            0,
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Código abreviado de variante
    |--------------------------------------------------------------------------
    */

    function variantCode(value) {

        const word = normalizeText(value)
            .split(' ')
            .filter(Boolean)[0]
            || 'VAR';


        return word.slice(
            0,
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prefijo SKU sugerido
    |--------------------------------------------------------------------------
    */

    function suggestedPrefix() {

        const product = productCode(
            productNameInput?.value || ''
        );

        const brand = brandCode(
            brandInput?.value || ''
        );


        return [
            product,
            brand,
        ]
            .filter(Boolean)
            .join('-');
    }


    /*
    |--------------------------------------------------------------------------
    | Normalizar números para SKU
    |--------------------------------------------------------------------------
    |
    | 4     -> 4
    | 3.5   -> 3P5
    | 650   -> 650
    |
    */

    function normalizeNumber(value) {

        if (
            value === ''
            ||
            value === null
            ||
            value === undefined
        ) {
            return '';
        }


        const number = Number(value);


        if (
            !Number.isFinite(number)
            ||
            number <= 0
        ) {
            return '';
        }


        return String(number)
            .replace(
                '.',
                'P'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Obtener variantes existentes en el formulario
    |--------------------------------------------------------------------------
    */

    function getVariants() {

        return variantRows()
            .map((row) => ({

                key:
                    row.querySelector(
                        '[data-variant-key]'
                    )?.value
                    || '',

                type:
                    row.querySelector(
                        '[data-variant-type]'
                    )?.value.trim()
                    || '',

                value:
                    row.querySelector(
                        '[data-variant-value]'
                    )?.value.trim()
                    || '',

                state:
                    row.querySelector(
                        '[data-child-status]'
                    )?.value
                    || '1',

            }))
            .filter(
                (variant) =>
                    variant.key
                    &&
                    variant.value
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar prefijo automático
    |--------------------------------------------------------------------------
    */

    function updatePrefixIfAutomatic() {

        if (!prefixInput) {
            return;
        }


        if (
            prefixInput.dataset.manual
            === '1'
        ) {
            return;
        }


        prefixInput.value =
            suggestedPrefix();


        updateAllSkuPreviews();
    }



    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | VARIANTES AGRUPADAS
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Actualizar tipo oculto de cada valor
    |--------------------------------------------------------------------------
    */

    function updateGroupHiddenTypes(group) {

        const type = group
            .querySelector(
                '[data-variant-group-type]'
            )
            ?.value
            .trim()
            || '';


        group
            .querySelectorAll(
                '[data-variant-type]'
            )
            .forEach((input) => {

                input.value = type;

            });


        const title = group.querySelector(
            '[data-variant-group-title]'
        );


        if (title) {

            title.textContent =
                type
                ||
                'Nuevo tipo de variante';

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar bloque de variante
    |--------------------------------------------------------------------------
    */

    function refreshVariantGroup(group) {

        const rows = [
            ...group.querySelectorAll(
                '[data-variant-row]'
            )
        ];


        const countInput = group.querySelector(
            '[data-variant-count]'
        );


        const summary = group.querySelector(
            '[data-variant-group-summary]'
        );


        const type = group
            .querySelector(
                '[data-variant-group-type]'
            )
            ?.value
            .trim()
            || '';


        /*
        | Numerar valores
        */

        rows.forEach(
            (row, index) => {

                const number = row.querySelector(
                    '[data-variant-number]'
                );


                if (number) {

                    number.textContent =
                        String(index + 1);

                }

            }
        );


        /*
        | Mantener cantidad sincronizada
        */

        if (countInput) {

            countInput.value =
                String(
                    rows.length || 1
                );

        }


        /*
        | Resumen cuando se minimiza
        */

        if (summary) {

            summary.textContent =
                `${rows.length} valor`
                +
                `${rows.length === 1 ? '' : 'es'}`
                +
                `${type ? ` · ${type}` : ''}`;

        }


        updateGroupHiddenTypes(
            group
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Agregar un valor a un tipo de variante
    |--------------------------------------------------------------------------
    */

    function addVariantValue(group) {

        if (!variantValueTemplate) {
            return null;
        }


        const key =
            `tmp_${temporaryKey++}`;


        const html =
            variantValueTemplate
                .innerHTML
                .replaceAll(
                    '__INDEX__',
                    variantIndex++
                )
                .replaceAll(
                    '__CLAVE__',
                    key
                );


        const values = group.querySelector(
            '[data-variant-values]'
        );


        values?.insertAdjacentHTML(
            'beforeend',
            html
        );


        const row = values
            ?.querySelector(
                '[data-variant-row]:last-child'
            )
            || null;


        refreshVariantGroup(
            group
        );


        applyProductStatusRules();

        updateAllVariantOptions();


        return row;
    }


    /*
    |--------------------------------------------------------------------------
    | Cambiar cantidad de variantes
    |--------------------------------------------------------------------------
    |
    | Ejemplo:
    |
    | Tipo: Aroma
    | Cantidad: 6
    |
    | Se generan 6 campos.
    |
    */

    function setVariantGroupCount(
        group,
        requestedCount
    ) {

        const count = Math.max(
            1,
            Math.min(
                50,
                Number(requestedCount) || 1
            )
        );


        const values = group.querySelector(
            '[data-variant-values]'
        );


        if (!values) {
            return;
        }


        let rows = [
            ...values.querySelectorAll(
                '[data-variant-row]'
            )
        ];


        /*
        | Agregar campos faltantes
        */

        while (
            rows.length < count
        ) {

            addVariantValue(
                group
            );


            rows = [
                ...values.querySelectorAll(
                    '[data-variant-row]'
                )
            ];

        }


        /*
        | Eliminar campos sobrantes
        */

        while (
            rows.length > count
        ) {

            rows
                .at(-1)
                ?.remove();


            rows = [
                ...values.querySelectorAll(
                    '[data-variant-row]'
                )
            ];

        }


        refreshVariantGroup(
            group
        );


        updateAllVariantOptions();
    }


    /*
    |--------------------------------------------------------------------------
    | Agregar nuevo tipo de variante
    |--------------------------------------------------------------------------
    |
    | Ejemplo:
    |
    | Aroma
    | Color
    |
    */

    function addVariantGroup() {

        if (
            !variantGroupTemplate
            ||
            !variantList
        ) {
            return;
        }


        variantList.insertAdjacentHTML(
            'beforeend',
            variantGroupTemplate.innerHTML
        );


        const group =
            variantGroups().at(-1);


        if (!group) {
            return;
        }


        /*
        | Todo nuevo grupo comienza
        | con un valor.
        */

        addVariantValue(
            group
        );


        refreshVariantGroup(
            group
        );


        setCollapsed(
            group,
            false
        );


        const emptyHelp = form.querySelector(
            '[data-no-variant-groups]'
        );


        if (emptyHelp) {

            emptyHelp.style.display =
                'none';

        }
    }



    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | PRESENTACIONES Y SKU
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Reconstruir opciones de variantes
    |--------------------------------------------------------------------------
    */

    function rebuildVariantOptions(card) {

        const container = card.querySelector(
            '[data-variant-options]'
        );


        if (!container) {
            return;
        }


        /*
        | Guardar cuáles estaban marcadas.
        */

        const selected = new Set(

            [
                ...container.querySelectorAll(
                    '[data-presentation-variant]:checked'
                )
            ]
                .map(
                    (input) =>
                        input.value
                )

        );


        const variants =
            getVariants();


        const index =
            card.dataset.presentationIndex;


        container.innerHTML = '';


        /*
        | Producto sin variantes
        */

        if (!variants.length) {

            const empty =
                document.createElement(
                    'div'
                );


            empty.className =
                'muted';


            empty.dataset.noVariants =
                '';


            empty.textContent =
                'Este producto todavía no tiene variantes registradas.';


            container.appendChild(
                empty
            );


            updateSkuPreview(
                card
            );


            updatePresentationSummary(
                card
            );


            return;
        }


        /*
        | Crear checkbox por variante
        */

        variants.forEach(
            (variant) => {

                const label =
                    document.createElement(
                        'label'
                    );


                label.className =
                    `variant-check-item`
                    +
                    `${
                        variant.state === '0'
                            ? ' is-inactive'
                            : ''
                    }`;


                const checkbox =
                    document.createElement(
                        'input'
                    );


                checkbox.type =
                    'checkbox';


                checkbox.name =
                    `presentaciones`
                    +
                    `[${index}]`
                    +
                    `[variantes_clave][]`;


                checkbox.value =
                    variant.key;


                checkbox.dataset.presentationVariant =
                    '';


                checkbox.checked =
                    selected.has(
                        variant.key
                    );


                const text =
                    document.createElement(
                        'span'
                    );


                const strong =
                    document.createElement(
                        'strong'
                    );


                strong.textContent =
                    variant.value;


                const small =
                    document.createElement(
                        'small'
                    );


                small.textContent =
                    `${variant.type || 'Variante'}`
                    +
                    `${
                        variant.state === '0'
                            ? ' · Inactiva'
                            : ''
                    }`;


                text.appendChild(
                    strong
                );


                text.appendChild(
                    small
                );


                label.appendChild(
                    checkbox
                );


                label.appendChild(
                    text
                );


                container.appendChild(
                    label
                );

            }
        );


        updateSkuPreview(
            card
        );


        updatePresentationSummary(
            card
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar opciones de variantes
    | en todas las presentaciones
    |--------------------------------------------------------------------------
    */

    function updateAllVariantOptions() {

        presentationRows()
            .forEach(
                rebuildVariantOptions
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Vista previa SKU
    |--------------------------------------------------------------------------
    */

    function updateSkuPreview(card) {

        const preview = card.querySelector(
            '[data-sku-preview]'
        );


        if (!preview) {
            return;
        }


        const prefix = cleanSkuPart(
            prefixInput?.value || ''
        );


        const content = normalizeNumber(
            card
                .querySelector(
                    '[data-presentation-content]'
                )
                ?.value
                || ''
        );


        const unit = normalizeText(
            card
                .querySelector(
                    '[data-presentation-unit]'
                )
                ?.value
                || ''
        );


        const selectedKeys = [

            ...card.querySelectorAll(
                '[data-presentation-variant]:checked'
            )

        ].map(
            (input) =>
                input.value
        );


        const variants =
            getVariants();


        const selectedVariants =
            variants.filter(
                (variant) =>
                    selectedKeys.includes(
                        variant.key
                    )
            );


        preview.innerHTML = '';


        /*
        | Aún faltan datos
        */

        if (
            !prefix
            ||
            !content
            ||
            !unit
        ) {

            const text =
                document.createElement(
                    'span'
                );


            text.className =
                'muted';


            text.textContent =
                'Completa el nombre, la marca y el contenido para ver la vista previa.';


            preview.appendChild(
                text
            );


            return;
        }


        /*
        | Si hay variantes seleccionadas:
        | genera un SKU por variante.
        |
        | Si no:
        | genera SKU normal.
        */

        const values =
            selectedVariants.length
                ? selectedVariants
                : [null];


        values.forEach(
            (variant) => {

                const sku = [

                    prefix,

                    variant
                        ? variantCode(
                            variant.value
                        )
                        : '',

                    `${content}${unit}`,

                ]
                    .filter(Boolean)
                    .join('-');


                const badge =
                    document.createElement(
                        'span'
                    );


                badge.className =
                    'sku-preview-item';


                badge.textContent =
                    sku;


                preview.appendChild(
                    badge
                );

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar todos los SKU
    |--------------------------------------------------------------------------
    */

    function updateAllSkuPreviews() {

        presentationRows()
            .forEach(
                updateSkuPreview
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Mostrar campos según forma de venta
    |--------------------------------------------------------------------------
    */

    function updateSaleVisibility(card) {

        const unit =
            card.querySelector(
                '[data-sale-unit]'
            );


        const pack =
            card.querySelector(
                '[data-sale-pack]'
            );


        const unitFields =
            card.querySelector(
                '[data-unit-fields]'
            );


        const packFields =
            card.querySelector(
                '[data-pack-fields]'
            );


        if (unitFields) {

            unitFields.style.display =
                unit?.checked
                    ? 'block'
                    : 'none';

        }


        if (packFields) {

            packFields.style.display =
                pack?.checked
                    ? 'block'
                    : 'none';

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Resumen de presentación minimizada
    |--------------------------------------------------------------------------
    */

    function updatePresentationSummary(card) {

        const summary =
            card.querySelector(
                '[data-presentation-summary]'
            );


        if (!summary) {
            return;
        }


        const envase =
            card.querySelector(
                'input[name$="[tipo_envase]"]'
            )
            ?.value
            .trim()
            || '';


        const contenido =
            card.querySelector(
                '[data-presentation-content]'
            )
            ?.value
            || '';


        const unidad =
            card.querySelector(
                '[data-presentation-unit]'
            )
            ?.value
            || '';


        const selected =
            card.querySelectorAll(
                '[data-presentation-variant]:checked'
            ).length;


        const parts = [];


        if (envase) {

            parts.push(
                envase
            );

        }


        if (
            contenido
            &&
            unidad
        ) {

            parts.push(
                `${contenido} ${unidad}`
            );

        }


        if (selected) {

            parts.push(
                `${selected} variante`
                +
                `${selected === 1 ? '' : 's'}`
            );

        }


        summary.textContent =
            parts.length
                ? parts.join(' · ')
                : 'El SKU se genera automáticamente.';
    }


    /*
    |--------------------------------------------------------------------------
    | Numerar presentaciones
    |--------------------------------------------------------------------------
    */

    function refreshPresentationTitles() {

        presentationRows()
            .forEach(
                (row, index) => {

                    const title =
                        row.querySelector(
                            '[data-presentation-title]'
                        );


                    if (title) {

                        title.textContent =
                            `Presentación ${index + 1}`;

                    }


                    updatePresentationSummary(
                        row
                    );

                }
            );
    }



    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | ESTADOS JERÁRQUICOS
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Producto inactivo
    |--------------------------------------------------------------------------
    |
    | Si el producto está inactivo:
    |
    | - Variantes quedan inactivas.
    | - Presentaciones quedan inactivas.
    | - No pueden activarse mientras
    |   el producto siga inactivo.
    |
    */

    function applyProductStatusRules() {

        const productInactive =
            productStatusInput?.value
            === '0';


        form.classList.toggle(
            'product-inactive-mode',
            productInactive
        );


        childStatusInputs()
            .forEach(
                (select) => {

                    /*
                    |--------------------------------------------------------------------------
                    | Producto inactivo
                    |--------------------------------------------------------------------------
                    */

                    if (productInactive) {

                        /*
                        | Guardamos estado anterior
                        | para recuperarlo si el usuario
                        | reactiva el producto antes
                        | de guardar.
                        */

                        if (
                            select.dataset.parentLocked
                            !== '1'
                        ) {

                            select.dataset.previousState =
                                select.value;

                        }


                        /*
                        | Forzar inactivo
                        */

                        select.value =
                            '0';


                        select.disabled =
                            true;


                        select.dataset.parentLocked =
                            '1';


                        select
                            .closest(
                                '.form-group'
                            )
                            ?.classList
                            .add(
                                'child-status-locked'
                            );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Producto nuevamente activo
                    |--------------------------------------------------------------------------
                    */

                    else if (
                        select.dataset.parentLocked
                        === '1'
                    ) {

                        select.disabled =
                            false;


                        /*
                        | Recuperamos el estado
                        | que tenía antes.
                        */

                        if (
                            select.dataset.previousState
                            !== undefined
                        ) {

                            select.value =
                                select.dataset.previousState;

                        }


                        delete select
                            .dataset
                            .previousState;


                        delete select
                            .dataset
                            .parentLocked;


                        select
                            .closest(
                                '.form-group'
                            )
                            ?.classList
                            .remove(
                                'child-status-locked'
                            );

                    }

                }
            );
    }



    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | COLAPSAR / EXPANDIR
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Cambiar estado visual de una sección
    |--------------------------------------------------------------------------
    */

    function setCollapsed(
        card,
        collapsed
    ) {

        if (!card) {
            return;
        }


        card.classList.toggle(
            'is-collapsed',
            collapsed
        );


        const icon =
            card.querySelector(
                '[data-collapse-icon]'
            );


        const button =
            card.querySelector(
                '[data-collapse-toggle]'
            );


        if (icon) {

            icon.textContent =
                collapsed
                    ? 'expand_more'
                    : 'expand_less';

        }


        if (button) {

            button.title =
                collapsed
                    ? 'Expandir sección'
                    : 'Minimizar sección';

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Contraer solo Variantes
    | o solo Presentaciones
    |--------------------------------------------------------------------------
    */

    function toggleCollapseAll(
        sectionName,
        button
    ) {

        const selector =
            sectionName === 'variants'

                ? '[data-variant-group]'

                : '[data-presentation-row]';


        const cards = [
            ...form.querySelectorAll(
                selector
            )
        ];


        if (!cards.length) {
            return;
        }


        /*
        | Si existe uno abierto:
        | contraemos todos.
        */

        const shouldCollapse =
            cards.some(
                (card) =>
                    !card.classList.contains(
                        'is-collapsed'
                    )
            );


        cards.forEach(
            (card) =>
                setCollapsed(
                    card,
                    shouldCollapse
                )
        );


        button.textContent =
            shouldCollapse
                ? 'Expandir todo'
                : 'Contraer todo';
    }


    /*
    |--------------------------------------------------------------------------
    | Contraer TODO el formulario
    |--------------------------------------------------------------------------
    |
    | Incluye:
    |
    | Información general
    | Imagen
    | Variantes
    | Presentaciones
    |
    */

    function toggleWholeForm(button) {

        const elements = [

            ...form.querySelectorAll(
                '[data-collapsible-section]'
            ),

            ...form.querySelectorAll(
                '[data-variant-group]'
            ),

            ...form.querySelectorAll(
                '[data-presentation-row]'
            ),

        ];


        if (!elements.length) {
            return;
        }


        /*
        | Si existe al menos una sección
        | abierta, contraemos todo.
        |
        | Si todo está contraído,
        | expandimos todo.
        */

        const shouldCollapse =
            elements.some(
                (element) =>
                    !element.classList.contains(
                        'is-collapsed'
                    )
            );


        elements.forEach(
            (element) => {

                setCollapsed(
                    element,
                    shouldCollapse
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Actualizar botón principal
        |--------------------------------------------------------------------------
        */

        const icon =
            button.querySelector(
                '[data-collapse-form-icon]'
            );


        const label =
            button.querySelector(
                '[data-collapse-form-label]'
            );


        if (icon) {

            icon.textContent =
                shouldCollapse
                    ? 'unfold_more'
                    : 'unfold_less';

        }


        if (label) {

            label.textContent =
                shouldCollapse
                    ? 'Expandir todo'
                    : 'Contraer todo';

        }


        /*
        |--------------------------------------------------------------------------
        | Sincronizar botones internos
        |--------------------------------------------------------------------------
        */

        form
            .querySelectorAll(
                '[data-collapse-all]'
            )
            .forEach(
                (sectionButton) => {

                    sectionButton.textContent =
                        shouldCollapse
                            ? 'Expandir todo'
                            : 'Contraer todo';

                }
            );
    }



    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | EVENTOS
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Agregar tipo de variante
    |--------------------------------------------------------------------------
    */

    addVariantButton
        ?.addEventListener(
            'click',
            addVariantGroup
        );


    /*
    |--------------------------------------------------------------------------
    | Agregar presentación
    |--------------------------------------------------------------------------
    */

    addPresentationButton
        ?.addEventListener(
            'click',
            () => {

                if (
                    !presentationTemplate
                    ||
                    !presentationList
                ) {
                    return;
                }


                const index =
                    presentationIndex++;


                const html =
                    presentationTemplate
                        .innerHTML
                        .replaceAll(
                            '__INDEX__',
                            index
                        );


                presentationList
                    .insertAdjacentHTML(
                        'beforeend',
                        html
                    );


                const row =
                    presentationRows()
                        .at(-1);


                if (row) {

                    row.dataset.presentationIndex =
                        index;


                    rebuildVariantOptions(
                        row
                    );


                    updateSaleVisibility(
                        row
                    );


                    updateSkuPreview(
                        row
                    );


                    updatePresentationSummary(
                        row
                    );


                    setCollapsed(
                        row,
                        false
                    );

                }


                applyProductStatusRules();

                refreshPresentationTitles();

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Clicks delegados
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'click',
        (event) => {

            const removeVariant =
                event.target.closest(
                    '[data-remove-variant]'
                );


            const removeVariantGroup =
                event.target.closest(
                    '[data-remove-variant-group]'
                );


            const addVariantValueButton =
                event.target.closest(
                    '[data-add-variant-value]'
                );


            const removePresentation =
                event.target.closest(
                    '[data-remove-presentation]'
                );


            const selectAll =
                event.target.closest(
                    '[data-select-all-variants]'
                );


            const clearAll =
                event.target.closest(
                    '[data-clear-variants]'
                );


            const collapseToggle =
                event.target.closest(
                    '[data-collapse-toggle]'
                );


            const collapseAll =
                event.target.closest(
                    '[data-collapse-all]'
                );


            const collapseForm =
                event.target.closest(
                    '[data-collapse-form]'
                );


            /*
            |--------------------------------------------------------------------------
            | Contraer / expandir formulario completo
            |--------------------------------------------------------------------------
            */

            if (collapseForm) {

                toggleWholeForm(
                    collapseForm
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Contraer una sección
            |--------------------------------------------------------------------------
            */

            if (collapseToggle) {

                const card =
                    collapseToggle.closest(
                        `
                        [data-collapsible-section],
                        [data-variant-group],
                        [data-presentation-row]
                        `
                    );


                if (card) {

                    setCollapsed(
                        card,
                        !card.classList.contains(
                            'is-collapsed'
                        )
                    );

                }


                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Contraer todas las variantes
            | o todas las presentaciones
            |--------------------------------------------------------------------------
            */

            if (collapseAll) {

                toggleCollapseAll(
                    collapseAll.dataset.collapseAll,
                    collapseAll
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Agregar valor a variante
            |--------------------------------------------------------------------------
            */

            if (addVariantValueButton) {

                const group =
                    addVariantValueButton
                        .closest(
                            '[data-variant-group]'
                        );


                if (group) {

                    addVariantValue(
                        group
                    );

                }


                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Eliminar un valor de variante
            |--------------------------------------------------------------------------
            */

            if (removeVariant) {

                const group =
                    removeVariant
                        .closest(
                            '[data-variant-group]'
                        );


                removeVariant
                    .closest(
                        '[data-variant-row]'
                    )
                    ?.remove();


                if (group) {

                    const rows =
                        group.querySelectorAll(
                            '[data-variant-row]'
                        );


                    /*
                    | No dejamos un grupo sin
                    | ningún campo.
                    */

                    if (!rows.length) {

                        addVariantValue(
                            group
                        );

                    }


                    refreshVariantGroup(
                        group
                    );

                }


                updateAllVariantOptions();

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Eliminar grupo completo
            |--------------------------------------------------------------------------
            */

            if (removeVariantGroup) {

                removeVariantGroup
                    .closest(
                        '[data-variant-group]'
                    )
                    ?.remove();


                updateAllVariantOptions();

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Eliminar presentación
            |--------------------------------------------------------------------------
            */

            if (removePresentation) {

                removePresentation
                    .closest(
                        '[data-presentation-row]'
                    )
                    ?.remove();


                refreshPresentationTitles();

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Seleccionar todas las variantes
            |--------------------------------------------------------------------------
            */

            if (selectAll) {

                const card =
                    selectAll.closest(
                        '[data-presentation-row]'
                    );


                card
                    ?.querySelectorAll(
                        '[data-presentation-variant]'
                    )
                    .forEach(
                        (input) => {

                            input.checked =
                                true;

                        }
                    );


                if (card) {

                    updateSkuPreview(
                        card
                    );


                    updatePresentationSummary(
                        card
                    );

                }


                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Limpiar variantes seleccionadas
            |--------------------------------------------------------------------------
            */

            if (clearAll) {

                const card =
                    clearAll.closest(
                        '[data-presentation-row]'
                    );


                card
                    ?.querySelectorAll(
                        '[data-presentation-variant]'
                    )
                    .forEach(
                        (input) => {

                            input.checked =
                                false;

                        }
                    );


                if (card) {

                    updateSkuPreview(
                        card
                    );


                    updatePresentationSummary(
                        card
                    );

                }
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Eventos input
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'input',
        (event) => {

            /*
            |--------------------------------------------------------------------------
            | Cambiar tipo de variante
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    '[data-variant-group-type]'
                )
            ) {

                const group =
                    event.target.closest(
                        '[data-variant-group]'
                    );


                if (group) {

                    updateGroupHiddenTypes(
                        group
                    );


                    refreshVariantGroup(
                        group
                    );

                }


                updateAllVariantOptions();
            }


            /*
            |--------------------------------------------------------------------------
            | Cambiar nombre de variante
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    '[data-variant-value]'
                )
            ) {

                updateAllVariantOptions();

            }


            /*
            |--------------------------------------------------------------------------
            | Nombre / Marca
            |
            | Actualizar SKU automático
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    '#nombre, #marca'
                )
            ) {

                updatePrefixIfAutomatic();

            }


            /*
            |--------------------------------------------------------------------------
            | Cambios de presentación
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    `
                    [data-presentation-content],
                    input[name$="[tipo_envase]"]
                    `
                )
            ) {

                const card =
                    event.target.closest(
                        '[data-presentation-row]'
                    );


                if (card) {

                    updateSkuPreview(
                        card
                    );


                    updatePresentationSummary(
                        card
                    );

                }
            }


            /*
            |--------------------------------------------------------------------------
            | Prefijo SKU manual
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    '[data-sku-prefix]'
                )
            ) {

                event.target.dataset.manual =
                    '1';


                event.target.value =
                    cleanSkuPart(
                        event.target.value
                    );


                updateAllSkuPreviews();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Eventos change
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'change',
        (event) => {

            /*
            |--------------------------------------------------------------------------
            | Cantidad de variantes
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    '[data-variant-count]'
                )
            ) {

                const group =
                    event.target.closest(
                        '[data-variant-group]'
                    );


                if (group) {

                    setVariantGroupCount(
                        group,
                        event.target.value
                    );

                }
            }


            /*
            |--------------------------------------------------------------------------
            | Estado de variante
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    `
                    [data-child-status]
                    [data-child-type="variante"]
                    `
                )
            ) {

                updateAllVariantOptions();

            }


            /*
            |--------------------------------------------------------------------------
            | Estado del producto
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    '[data-product-status]'
                )
            ) {

                applyProductStatusRules();

            }


            /*
            |--------------------------------------------------------------------------
            | Venta unidad / empaque
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    `
                    [data-sale-unit],
                    [data-sale-pack]
                    `
                )
            ) {

                const row =
                    event.target.closest(
                        '[data-presentation-row]'
                    );


                if (row) {

                    updateSaleVisibility(
                        row
                    );

                }
            }


            /*
            |--------------------------------------------------------------------------
            | Unidad de medida / variante seleccionada
            |--------------------------------------------------------------------------
            */

            if (
                event.target.matches(
                    `
                    [data-presentation-unit],
                    [data-presentation-variant]
                    `
                )
            ) {

                const row =
                    event.target.closest(
                        '[data-presentation-row]'
                    );


                if (row) {

                    updateSkuPreview(
                        row
                    );


                    updatePresentationSummary(
                        row
                    );

                }
            }

        }
    );



    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | IMAGEN
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */

    const imageInput =
        form.querySelector(
            '[data-image-input]'
        );


    const imagePreview =
        form.querySelector(
            '[data-image-preview]'
        );


    const imagePlaceholder =
        form.querySelector(
            '[data-image-placeholder]'
        );


    const removeImageButton =
        form.querySelector(
            '[data-remove-image]'
        );


    const removeImageFlag =
        form.querySelector(
            '[data-remove-image-flag]'
        );


    /*
    |--------------------------------------------------------------------------
    | Seleccionar imagen
    |--------------------------------------------------------------------------
    */

    imageInput
        ?.addEventListener(
            'change',
            () => {

                const file =
                    imageInput.files?.[0];


                if (!file) {
                    return;
                }


                if (imagePreview) {

                    imagePreview.src =
                        URL.createObjectURL(
                            file
                        );


                    imagePreview.style.display =
                        'block';

                }


                if (imagePlaceholder) {

                    imagePlaceholder.style.display =
                        'none';

                }


                if (removeImageButton) {

                    removeImageButton.style.display =
                        'inline-flex';

                }


                if (removeImageFlag) {

                    removeImageFlag.value =
                        '0';

                }

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Quitar imagen
    |--------------------------------------------------------------------------
    */

    removeImageButton
        ?.addEventListener(
            'click',
            () => {

                if (imageInput) {

                    imageInput.value =
                        '';

                }


                if (imagePreview) {

                    imagePreview.removeAttribute(
                        'src'
                    );


                    imagePreview.style.display =
                        'none';

                }


                if (imagePlaceholder) {

                    imagePlaceholder.style.display =
                        'grid';

                }


                if (removeImageButton) {

                    removeImageButton.style.display =
                        'none';

                }


                if (removeImageFlag) {

                    removeImageFlag.value =
                        '1';

                }

            }
        );



    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | INICIALIZACIÓN
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Prefijo SKU
    |--------------------------------------------------------------------------
    */

    if (prefixInput) {

        prefixInput.dataset.manual =
            prefixInput.value.trim()
                ? '1'
                : '0';


        if (
            !prefixInput.value.trim()
        ) {

            updatePrefixIfAutomatic();

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Información general e imagen
    |--------------------------------------------------------------------------
    */

    form
        .querySelectorAll(
            '[data-collapsible-section]'
        )
        .forEach(
            (section) => {

                setCollapsed(
                    section,
                    false
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Variantes
    |--------------------------------------------------------------------------
    */

    variantGroups()
        .forEach(
            (group) => {

                refreshVariantGroup(
                    group
                );


                setCollapsed(
                    group,
                    false
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Presentaciones
    |--------------------------------------------------------------------------
    */

    presentationRows()
        .forEach(
            (row) => {

                updateSaleVisibility(
                    row
                );


                updateSkuPreview(
                    row
                );


                updatePresentationSummary(
                    row
                );


                setCollapsed(
                    row,
                    false
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Estados
    |--------------------------------------------------------------------------
    */

    applyProductStatusRules();


    /*
    |--------------------------------------------------------------------------
    | Títulos
    |--------------------------------------------------------------------------
    */

    refreshPresentationTitles();



    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | ENVÍO DEL FORMULARIO
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'submit',
        () => {

            /*
            | Los select deshabilitados
            | no viajan en el request.
            |
            | Si el producto está
            | inactivo, los habilitamos
            | justo antes de enviar,
            | manteniendo valor 0.
            |
            | El backend también vuelve
            | a validar esta regla.
            */

            if (
                productStatusInput?.value
                === '0'
            ) {

                childStatusInputs()
                    .forEach(
                        (select) => {

                            select.value =
                                '0';


                            select.disabled =
                                false;

                        }
                    );

            }

        }
    );

})();