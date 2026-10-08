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
        ? $producto->presentaciones->map(fn ($presentacion) => [
            'id' => $presentacion->id,
            'codigo_sku' => $presentacion->codigo_sku,
            'variante_clave' => $presentacion->variante_producto_id
                ? 'db_'.$presentacion->variante_producto_id
                : '',
            'tipo_envase' => $presentacion->tipo_envase,
            'contenido' => $presentacion->contenido,
            'unidad_medida' => $presentacion->unidad_medida,
            'tipo_empaque' => $presentacion->tipo_empaque,
            'unidades_por_empaque' => $presentacion->unidades_por_empaque,
            'venta_por_unidad' => $presentacion->venta_por_unidad ? '1' : '0',
            'venta_por_empaque' => $presentacion->venta_por_empaque ? '1' : '0',
            'precio_unitario' => $presentacion->precio_unitario,
            'precio_empaque' => $presentacion->precio_empaque,
            'estado' => $presentacion->estado ? '1' : '0',
        ])->values()->all()
        : [[
            'id' => null,
            'codigo_sku' => '',
            'variante_clave' => '',
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
@endphp

<form
    method="POST"
    action="{{ $editing ? route('productos.update', $producto) : route('productos.store') }}"
    enctype="multipart/form-data"
    data-product-form
>
    @csrf
    @if($editing)
        @method('PUT')
    @endif

    <section class="form-section">
        <h2 class="form-section-title">Información general</h2>

        <div class="form-grid">
            <div class="form-group">
                <label for="codigo" class="form-label">Código *</label>
                <input
                    type="text"
                    id="codigo"
                    name="codigo"
                    value="{{ old('codigo', $editing ? $producto->codigo : $codigoSugerido) }}"
                    class="form-control"
                    maxlength="20"
                    required
                >
            </div>

            <div class="form-group">
                <label for="nombre" class="form-label">Nombre del producto *</label>
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre', $editing ? $producto->nombre : '') }}"
                    class="form-control"
                    maxlength="150"
                    required
                >
            </div>

            <div class="form-group">
                <label for="categoria_id" class="form-label">Categoría *</label>
                <select id="categoria_id" name="categoria_id" class="form-control" required>
                    <option value="">Seleccione una categoría</option>
                    @foreach($categorias as $categoria)
                        <option
                            value="{{ $categoria->id }}"
                            {{ (string) old('categoria_id', $editing ? $producto->categoria_id : '') === (string) $categoria->id ? 'selected' : '' }}
                        >
                            {{ $categoria->nombre }}{{ !$categoria->estado ? ' (Inactiva)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="marca" class="form-label">Marca</label>
                <input
                    type="text"
                    id="marca"
                    name="marca"
                    value="{{ old('marca', $editing ? $producto->marca : '') }}"
                    class="form-control"
                    placeholder="Ej. Eco Limp"
                    maxlength="100"
                >
            </div>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea
                id="descripcion"
                name="descripcion"
                class="form-control products-textarea"
                maxlength="1000"
                placeholder="Descripción comercial del producto..."
            >{{ old('descripcion', $editing ? $producto->descripcion : '') }}</textarea>
        </div>

        <div class="form-group" style="margin-top:20px; max-width:320px;">
            <label for="estado" class="form-label">Estado *</label>
            @php
                $estadoProducto = (string) old('estado', $editing ? (string) (int) $producto->estado : '1');
            @endphp
            <select id="estado" name="estado" class="form-control" required>
                <option value="1" {{ $estadoProducto === '1' ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ $estadoProducto === '0' ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>
    </section>

    <section class="form-section">
        <h2 class="form-section-title">Imagen de referencia</h2>

        <div class="image-upload">
            <div class="image-preview">
                <img
                    data-image-preview
                    src="{{ $editing && $producto->imagen_referencia ? asset('storage/'.$producto->imagen_referencia) : '' }}"
                    alt="Vista previa"
                    style="{{ $editing && $producto->imagen_referencia ? '' : 'display:none;' }}"
                >

                <div
                    data-image-placeholder
                    style="{{ $editing && $producto->imagen_referencia ? 'display:none;' : 'display:grid;' }}"
                >
                    <span class="material-symbols-outlined">add_photo_alternate</span>
                </div>
            </div>

            <div>
                <div class="form-group">
                    <label for="imagen_referencia" class="form-label">Seleccionar imagen</label>
                    <input
                        type="file"
                        id="imagen_referencia"
                        name="imagen_referencia"
                        accept="image/jpeg,image/png,image/webp"
                        data-image-input
                    >
                    <span class="form-help">JPG, JPEG, PNG o WEBP. Máximo 4 MB.</span>
                </div>

                <input type="hidden" name="eliminar_imagen" value="0" data-remove-image-flag>

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-remove-image
                    style="margin-top:14px; {{ $editing && $producto->imagen_referencia ? '' : 'display:none;' }}"
                >
                    <span class="material-symbols-outlined">delete</span>
                    Quitar imagen
                </button>
            </div>
        </div>
    </section>

    @include('products.partials.variantes')
    @include('products.partials.presentaciones')

    <div class="form-actions">
        <a
            href="{{ $editing ? route('productos.show', $producto) : route('productos.index') }}"
            class="btn btn-secondary"
        >
            Cancelar
        </a>

        <button type="submit" class="btn btn-primary">
            <span class="material-symbols-outlined">save</span>
            {{ $editing ? 'Guardar cambios' : 'Guardar producto' }}
        </button>
    </div>
</form>

@push('scripts')
<script src="{{ asset('js/products-form.js') }}?v=1"></script>
@endpush
