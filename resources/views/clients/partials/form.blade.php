
@php
    $editando = isset($cliente);
@endphp

<form
    method="POST"
    action="{{ $editando ? route('clientes.update', $cliente) : route('clientes.store') }}"
>
    @csrf

    @if($editando)
        @method('PUT')
    @endif

    <section class="form-section">
        <h2 class="form-section-title">Información del cliente</h2>

        <div class="form-grid">
            @foreach([
                'nombre' => ['Nombre / Razón social', 180],
                'documento' => ['DNI / RUC / CE (opcional)', 12],
                'telefono' => ['Teléfono', 30],
                'correo' => ['Correo electrónico (opcional)', 180],
            ] as $campo => $datos)
                <div class="form-group">
                    <label for="{{ $campo }}" class="form-label">
                        {{ $datos[0] }}
                        @if($campo === 'nombre') * @endif
                    </label>

                    <input
                        type="{{ $campo === 'correo' ? 'email' : ($campo === 'telefono' ? 'tel' : 'text') }}"
                        id="{{ $campo }}"
                        name="{{ $campo }}"
                        class="form-control"
                        maxlength="{{ $datos[1] }}"
                        value="{{ old($campo, $cliente->{$campo} ?? '') }}"
                        @required($campo === 'nombre')
                        @if($campo === 'nombre') minlength="3" @endif
                    >

                    @error($campo)
                        <small style="color:#dc2626;">{{ $message }}</small>
                    @enderror
                </div>
            @endforeach
        </div>
    </section>

    <section class="form-section">
        <h2 class="form-section-title">Ubicación del cliente</h2>

        <div class="form-grid">
            @foreach([
                'mercado' => ['Mercado', 150],
                'puesto_referencia' => ['Puesto / Referencia', 180],
                'distrito' => ['Distrito', 120],
                'direccion' => ['Dirección / Avenida', 255],
            ] as $campo => $datos)
                <div class="form-group">
                    <label for="{{ $campo }}" class="form-label">
                        {{ $datos[0] }}
                    </label>

                    <input
                        type="text"
                        id="{{ $campo }}"
                        name="{{ $campo }}"
                        class="form-control"
                        maxlength="{{ $datos[1] }}"
                        value="{{ old($campo, $cliente->{$campo} ?? '') }}"
                    >

                    @error($campo)
                        <small style="color:#dc2626;">{{ $message }}</small>
                    @enderror
                </div>
            @endforeach
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
            Cancelar
        </a>

        <button type="submit" class="btn btn-primary">
            <span class="material-symbols-outlined">save</span>
            {{ $editando ? 'Guardar cambios' : 'Registrar cliente' }}
        </button>
    </div>
</form>
