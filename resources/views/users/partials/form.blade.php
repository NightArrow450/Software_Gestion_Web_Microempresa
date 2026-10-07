@php
    $editing = isset($user);
@endphp

<form
    method="POST"
    action="{{ $editing
        ? route('usuarios.update', $user)
        : route('usuarios.store')
    }}"
>
    @csrf

    @if($editing)
        @method('PUT')
    @endif

    {{-- INFORMACIÓN PERSONAL --}}
    <section class="form-section">
        <h2 class="form-section-title">Información personal</h2>

        <div class="form-grid">

            <div class="form-group">
                <label for="first_name" class="form-label">Nombres *</label>

                <input
                    type="text"
                    id="first_name"
                    name="first_name"
                    value="{{ old('first_name', $editing ? $user->first_name : '') }}"
                    class="form-control"
                    placeholder="Ingrese los nombres"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-group">
                <label for="last_name" class="form-label">Apellidos *</label>

                <input
                    type="text"
                    id="last_name"
                    name="last_name"
                    value="{{ old('last_name', $editing ? $user->last_name : '') }}"
                    class="form-control"
                    placeholder="Ingrese los apellidos"
                    maxlength="100"
                    required
                >
            </div>

        </div>
    </section>

    {{-- INFORMACIÓN DE ACCESO --}}
    <section class="form-section">
        <h2 class="form-section-title">Información de acceso</h2>

        <div class="form-group">
            <label for="email" class="form-label">Correo electrónico *</label>

            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email', $editing ? $user->email : '') }}"
                class="form-control"
                placeholder="usuario@ejemplo.com"
                maxlength="255"
                autocomplete="email"
                required
            >
        </div>

        <div
            class="form-grid"
            style="margin-top:20px;"
        >
            <div class="form-group">
                <label for="password" class="form-label">
                    {{ $editing ? 'Nueva contraseña' : 'Contraseña *' }}
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="{{ $editing ? 'Dejar vacío para conservar la actual' : 'Mínimo 8 caracteres' }}"
                    autocomplete="new-password"
                    @if(!$editing) required @endif
                >

                @if($editing)
                    <span class="form-help">
                        Déjala vacía si no deseas cambiar la contraseña.
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">
                    {{ $editing ? 'Confirmar nueva contraseña' : 'Confirmar contraseña *' }}
                </label>

                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    class="form-control"
                    autocomplete="new-password"
                    @if(!$editing) required @endif
                >
            </div>
        </div>
    </section>

    {{-- CONFIGURACIÓN --}}
    <section class="form-section">
        <h2 class="form-section-title">Configuración</h2>

        <div class="form-grid">

            <div class="form-group">
                <label for="role_id" class="form-label">Rol *</label>

                <select
                    id="role_id"
                    name="role_id"
                    class="form-control"
                    required
                >
                    <option value="">Seleccione un rol</option>

                    @foreach($roles as $role)
                        <option
                            value="{{ $role->id }}"
                            {{ (string) old('role_id', $editing ? $user->role_id : '') === (string) $role->id ? 'selected' : '' }}
                        >
                            {{ $role->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Estado *</label>

                @php
                    $estadoActual = (string) old(
                        'status',
                        $editing ? (string) $user->status : '1'
                    );
                @endphp

                <select
                    id="status"
                    name="status"
                    class="form-control"
                    required
                >
                    <option value="1" {{ $estadoActual === '1' ? 'selected' : '' }}>
                        Activo
                    </option>

                    <option value="0" {{ $estadoActual === '0' ? 'selected' : '' }}>
                        Inactivo
                    </option>
                </select>
            </div>

        </div>
    </section>

    <div class="form-actions">
        <a
            href="{{ $editing
                ? route('usuarios.show', $user)
                : route('usuarios.index')
            }}"
            class="btn btn-secondary"
        >
            Cancelar
        </a>

        <button
            type="submit"
            class="btn btn-primary"
        >
            <span class="material-symbols-outlined">save</span>
            {{ $editing ? 'Guardar Cambios' : 'Guardar Usuario' }}
        </button>
    </div>
</form>
