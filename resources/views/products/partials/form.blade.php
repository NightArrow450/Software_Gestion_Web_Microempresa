@php
    $editing = isset($producto);

    $variantesBase = $editing
        ? $producto->variantes->map(fn ($variante) => [
            'id' => $variante->id,
            'clave' => 'db_'.$variante->id,
            'tipo' => $variante->tipo,
            'valor' => $variante->valor,
            'estado' => $variante->estado ? '1' : '0',
        ])->values()->all()
        : [];

    $presentacionesBase = $editing
        ? $producto->presentaciones
            ->groupBy(function ($presentacion) {
                return implode('|', [
                    $presentacion->tipo_envase ?? '',
                    (string) $presentacion->contenido,
                    $presentacion->unidad_medida,
                    $presentacion->tipo_empaque ?? '',
                    (string) ($presentacion->unidades_por_empaque ?? ''),
                    $presentacion->venta_por_unidad ? '1' : '0',
                    $presentacion->venta_por_empaque ? '1' : '0',
                    (string) ($presentacion->precio_unitario ?? ''),
                    (string) ($presentacion->precio_empaque ?? ''),
                    $presentacion->estado ? '1' : '0',
                ]);
            })
            ->map(function ($grupo) {
                $primera = $grupo->first();

                return [
                    'ids' => $grupo->mapWithKeys(fn ($presentacion) => [
                        ($presentacion->variante_producto_id
                            ? 'db_'.$presentacion->variante_producto_id
                            : '__none__') => $presentacion->id,
                    ])->all(),

                    'variantes_clave' => $grupo
                        ->pluck('variante_producto_id')
                        ->filter()
                        ->map(fn ($id) => 'db_'.$id)
                        ->values()
                        ->all(),

                    'tipo_envase' => $primera->tipo_envase,
                    'contenido' => $primera->contenido,
                    'unidad_medida' => $primera->unidad_medida,
                    'tipo_empaque' => $primera->tipo_empaque,
                    'unidades_por_empaque' => $primera->unidades_por_empaque,
                    'venta_por_unidad' => $primera->venta_por_unidad ? '1' : '0',
                    'venta_por_empaque' => $primera->venta_por_empaque ? '1' : '0',
                    'precio_unitario' => $primera->precio_unitario,
                    'precio_empaque' => $primera->precio_empaque,
                    'estado' => $primera->estado ? '1' : '0',
                ];
            })
            ->values()
            ->all()
        : [[
            'ids' => [],
            'variantes_clave' => [],
            'tipo_envase' => '',
            'contenido' => '',
            'unidad_medida' => 'ml',
            'tipo_empaque' => '',
            'unidades_por_empaque' => '',
            'venta_por_unidad' => '1',
            'venta_por_empaque' => '0',
            'precio_unitario' => '',
            'precio_empaque' => '',
            'estado' => '1',
        ]];

    $variantesForm = old('variantes', $variantesBase);
    $presentacionesForm = old('presentaciones', $presentacionesBase);

    $estadoProducto = (string) old(
        'estado',
        $editing
            ? (string) (int) $producto->estado
            : '1'
    );
@endphp


<form
    method="POST"
    action="{{ $editing
        ? route('productos.update', $producto)
        : route('productos.store')
    }}"
    enctype="multipart/form-data"
    data-product-form
>

    @csrf

    @if($editing)
        @method('PUT')
    @endif


    {{-- ============================================================
        CONTROL GENERAL DEL FORMULARIO
    ============================================================ --}}

    <div class="form-compact-toolbar">

        <span class="muted">
            Puedes contraer las secciones para trabajar de forma más cómoda.
        </span>

        <button
            type="button"
            class="btn btn-secondary"
            data-collapse-form
        >
            <span
                class="material-symbols-outlined"
                data-collapse-form-icon
            >
                unfold_less
            </span>

            <span data-collapse-form-label>
                Contraer todo
            </span>
        </button>

    </div>



    {{-- ============================================================
        INFORMACIÓN GENERAL
    ============================================================ --}}

    <section
        class="form-section main-collapsible-section"
        data-collapsible-section
    >

        <div class="collapsible-section-head">

            <div>

                <h2
                    class="form-section-title"
                    style="margin-bottom:6px;"
                >
                    Información general
                </h2>

                <div class="muted">
                    Datos principales del producto.
                </div>

            </div>


            <button
                type="button"
                class="icon-button"
                title="Minimizar información general"
                data-collapse-toggle
            >
                <span
                    class="material-symbols-outlined"
                    data-collapse-icon
                >
                    expand_less
                </span>
            </button>

        </div>


        <div data-collapsible-body>

            <div class="collapsible-section-content">

                <div class="form-grid">

                    {{-- CÓDIGO --}}

                    <div class="form-group">

                        <label
                            for="codigo"
                            class="form-label"
                        >
                            Código *
                        </label>

                        <input
                            type="text"
                            id="codigo"
                            name="codigo"
                            value="{{ old(
                                'codigo',
                                $editing
                                    ? $producto->codigo
                                    : $codigoSugerido
                            ) }}"
                            class="form-control"
                            maxlength="20"
                            required
                        >

                    </div>


                    {{-- NOMBRE --}}

                    <div class="form-group">

                        <label
                            for="nombre"
                            class="form-label"
                        >
                            Nombre del producto *
                        </label>

                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            value="{{ old(
                                'nombre',
                                $editing
                                    ? $producto->nombre
                                    : ''
                            ) }}"
                            class="form-control"
                            maxlength="150"
                            required
                        >

                    </div>


                    {{-- CATEGORÍA --}}

                    <div class="form-group">

                        <label
                            for="categoria_id"
                            class="form-label"
                        >
                            Categoría *
                        </label>

                        <select
                            id="categoria_id"
                            name="categoria_id"
                            class="form-control"
                            required
                        >

                            <option value="">
                                Seleccione una categoría
                            </option>

                            @foreach($categorias as $categoria)

                                <option
                                    value="{{ $categoria->id }}"
                                    {{
                                        (string) old(
                                            'categoria_id',
                                            $editing
                                                ? $producto->categoria_id
                                                : ''
                                        ) === (string) $categoria->id

                                        ? 'selected'
                                        : ''
                                    }}
                                >
                                    {{ $categoria->nombre }}

                                    @if(!$categoria->estado)
                                        (Inactiva)
                                    @endif
                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- MARCA --}}

                    <div class="form-group">

                        <label
                            for="marca"
                            class="form-label"
                        >
                            Marca
                        </label>

                        <input
                            type="text"
                            id="marca"
                            name="marca"
                            value="{{ old(
                                'marca',
                                $editing
                                    ? $producto->marca
                                    : ''
                            ) }}"
                            class="form-control"
                            placeholder="Ej. Eco Limp"
                            maxlength="100"
                        >

                    </div>

                </div>


                {{-- DESCRIPCIÓN --}}

                <div
                    class="form-group"
                    style="margin-top:20px;"
                >

                    <label
                        for="descripcion"
                        class="form-label"
                    >
                        Descripción
                    </label>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        class="form-control products-textarea"
                        maxlength="1000"
                        placeholder="Descripción comercial del producto..."
                    >{{ old(
                        'descripcion',
                        $editing
                            ? $producto->descripcion
                            : ''
                    ) }}</textarea>

                </div>


                {{-- ESTADO --}}

                <div
                    class="form-group"
                    style="
                        margin-top:20px;
                        max-width:320px;
                    "
                >

                    <label
                        for="estado"
                        class="form-label"
                    >
                        Estado *
                    </label>

                    <select
                        id="estado"
                        name="estado"
                        class="form-control"
                        data-product-status
                        required
                    >

                        <option
                            value="1"
                            {{ $estadoProducto === '1'
                                ? 'selected'
                                : ''
                            }}
                        >
                            Activo
                        </option>

                        <option
                            value="0"
                            {{ $estadoProducto === '0'
                                ? 'selected'
                                : ''
                            }}
                        >
                            Inactivo
                        </option>

                    </select>


                    <span
                        class="form-help"
                        data-product-status-help
                    >
                        Si el producto se desactiva, todas sus variantes
                        y presentaciones también quedarán inactivas.
                    </span>

                </div>

            </div>

        </div>

    </section>



    {{-- ============================================================
        IMAGEN DE REFERENCIA
    ============================================================ --}}

    <section
        class="form-section main-collapsible-section"
        data-collapsible-section
    >

        <div class="collapsible-section-head">

            <div>

                <h2
                    class="form-section-title"
                    style="margin-bottom:6px;"
                >
                    Imagen de referencia
                </h2>

                <div class="muted">
                    Fotografía utilizada para identificar visualmente el producto.
                </div>

            </div>


            <button
                type="button"
                class="icon-button"
                title="Minimizar imagen de referencia"
                data-collapse-toggle
            >
                <span
                    class="material-symbols-outlined"
                    data-collapse-icon
                >
                    expand_less
                </span>
            </button>

        </div>


        <div data-collapsible-body>

            <div class="collapsible-section-content">

                <div class="image-upload">


                    {{-- VISTA PREVIA --}}

                    <div class="image-preview">

                        <img
                            data-image-preview
                            src="{{
                                $editing
                                && $producto->imagen_referencia

                                    ? asset(
                                        'storage/'
                                        .$producto->imagen_referencia
                                    )

                                    : ''
                            }}"
                            alt="Vista previa"

                            style="{{
                                $editing
                                && $producto->imagen_referencia

                                    ? ''

                                    : 'display:none;'
                            }}"
                        >


                        <div
                            data-image-placeholder
                            style="{{
                                $editing
                                && $producto->imagen_referencia

                                    ? 'display:none;'

                                    : 'display:grid;'
                            }}"
                        >

                            <span class="material-symbols-outlined">
                                add_photo_alternate
                            </span>

                        </div>

                    </div>


                    {{-- CONTROLES DE IMAGEN --}}

                    <div>

                        <div class="form-group">

                            <label
                                for="imagen_referencia"
                                class="form-label"
                            >
                                Seleccionar imagen
                            </label>

                            <input
                                type="file"
                                id="imagen_referencia"
                                name="imagen_referencia"
                                accept="
                                    image/jpeg,
                                    image/png,
                                    image/webp
                                "
                                data-image-input
                            >

                            <span class="form-help">
                                JPG, JPEG, PNG o WEBP. Máximo 4 MB.
                            </span>

                        </div>


                        <input
                            type="hidden"
                            name="eliminar_imagen"
                            value="0"
                            data-remove-image-flag
                        >


                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-remove-image

                            style="
                                margin-top:14px;

                                {{
                                    $editing
                                    && $producto->imagen_referencia

                                        ? ''

                                        : 'display:none;'
                                }}
                            "
                        >

                            <span class="material-symbols-outlined">
                                delete
                            </span>

                            Quitar imagen

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </section>



    {{-- ============================================================
        VARIANTES
    ============================================================ --}}

    @include('products.partials.variantes')



    {{-- ============================================================
        PRESENTACIONES
    ============================================================ --}}

    @include('products.partials.presentaciones')



    {{-- ============================================================
        ACCIONES
    ============================================================ --}}

    <div class="form-actions">

        <a
            href="{{
                $editing
                    ? route('productos.show', $producto)
                    : route('productos.index')
            }}"
            class="btn btn-secondary"
        >
            Cancelar
        </a>


        <button
            type="submit"
            class="btn btn-primary"
        >

            <span class="material-symbols-outlined">
                save
            </span>

            {{
                $editing
                    ? 'Guardar cambios'
                    : 'Guardar producto'
            }}

        </button>

    </div>

</form>


@push('scripts')

    <script
        src="{{ asset('js/products-form.js') }}?v=4"
    ></script>

@endpush