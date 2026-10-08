@php
    $editing = isset($categoria);
@endphp

<form method="POST" action="{{ $editing ? route('categorias.update', $categoria) : route('categorias.store') }}">
    @csrf

    @if($editing)
        @method('PUT')
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:22px;">
            <span class="material-symbols-outlined">error</span>
            <div>
                <strong>Revisa la información ingresada.</strong>
                <ul style="margin:7px 0 0 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="form-section">
        <h2 class="form-section-title">Información de la categoría</h2>

        <div class="form-grid">
            <div class="form-group">
                <label for="codigo" class="form-label">Código *</label>
                <input
                    type="text"
                    id="codigo"
                    name="codigo"
                    value="{{ old('codigo', $editing ? $categoria->codigo : $codigoSugerido) }}"
                    class="form-control"
                    maxlength="10"
                    placeholder="CAT001"
                    oninput="this.value = this.value.toUpperCase()"
                    required
                >
                <div class="muted" style="margin-top:6px; font-size:12px;">
                    Formato sugerido: CAT001, CAT002, CAT003...
                </div>
            </div>

            <div class="form-group">
                <label for="nombre" class="form-label">Nombre *</label>
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre', $editing ? $categoria->nombre : '') }}"
                    class="form-control"
                    maxlength="100"
                    required
                >
            </div>
        </div>

        <div class="form-grid" style="margin-top:20px;">
            <div class="form-group">
                <label for="estado" class="form-label">Estado *</label>
                @php
                    $estadoCategoria = (string) old(
                        'estado',
                        $editing ? (string) (int) $categoria->estado : '1'
                    );
                @endphp

                <select id="estado" name="estado" class="form-control" required>
                    <option value="1" {{ $estadoCategoria === '1' ? 'selected' : '' }}>Activa</option>
                    <option value="0" {{ $estadoCategoria === '0' ? 'selected' : '' }}>Inactiva</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea
                id="descripcion"
                name="descripcion"
                class="form-control products-textarea"
                maxlength="255"
                placeholder="Descripción de la categoría..."
            >{{ old('descripcion', $editing ? $categoria->descripcion : '') }}</textarea>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ $editing ? route('categorias.show', $categoria) : route('categorias.index') }}" class="btn btn-secondary">
            Cancelar
        </a>

        <button type="submit" class="btn btn-primary">
            <span class="material-symbols-outlined">save</span>
            {{ $editing ? 'Guardar cambios' : 'Guardar categoría' }}
        </button>
    </div>
</form>
