<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Iniciar sesión | DHACER ECO LIMP</title>


    <script src="https://cdn.tailwindcss.com"></script>


    <script>
        tailwind.config = {
            darkMode: "class",

            theme: {
                extend: {

                    colors: {
                        "on-secondary": "#ffffff",
                        "on-error": "#ffffff",
                        "on-secondary-fixed-variant": "#00504a",
                        "surface-bright": "#faf8ff",
                        "secondary-container": "#99efe5",
                        "on-primary-fixed-variant": "#005137",
                        "error-container": "#ffdad6",
                        "on-secondary-fixed": "#00201d",
                        "error": "#ba1a1a",
                        "on-primary-fixed": "#002114",
                        "on-primary-container": "#f5fff7",
                        "tertiary-container": "#a36700",
                        "inverse-on-surface": "#eef0ff",
                        "outline": "#6d7a72",
                        "on-secondary-container": "#006f67",
                        "on-tertiary-fixed-variant": "#653e00",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#f2f3ff",
                        "on-background": "#131b2e",
                        "surface-container-high": "#e2e7ff",
                        "on-error-container": "#93000a",
                        "on-tertiary-container": "#fffbff",
                        "background": "#faf8ff",
                        "secondary": "#006a63",
                        "surface-variant": "#dae2fd",
                        "primary": "#006948",
                        "surface-container": "#eaedff",
                        "on-tertiary": "#ffffff",
                        "inverse-surface": "#283044",
                        "surface-dim": "#d2d9f4",
                        "outline-variant": "#bccac0",
                        "inverse-primary": "#68dba9",
                        "secondary-fixed": "#9cf2e8",
                        "surface": "#faf8ff",
                        "on-tertiary-fixed": "#2a1700",
                        "on-primary": "#ffffff",
                        "tertiary-fixed": "#ffddb8",
                        "secondary-fixed-dim": "#80d5cb",
                        "primary-fixed": "#85f8c4",
                        "primary-container": "#00855d",
                        "tertiary-fixed-dim": "#ffb95f",
                        "on-surface-variant": "#3d4a42",
                        "surface-tint": "#006c4a",
                        "tertiary": "#825100",
                        "primary-fixed-dim": "#68dba9",
                        "surface-container-highest": "#dae2fd",
                        "on-surface": "#131b2e"
                    },

                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg: "0.5rem",
                        xl: "0.75rem",
                        full: "9999px"
                    },

                    fontFamily: {
                        inter: ["Inter", "sans-serif"]
                    }

                }
            }
        };
    </script>


    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet"
    >


    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap"
        rel="stylesheet"
    >


    <style>

        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings:
                'FILL' 0,
                'wght' 400,
                'GRAD' 0,
                'opsz' 24;
        }

    </style>

</head>


<body class="bg-surface text-on-surface antialiased">


<main
    class="
        w-full
        min-h-screen
        flex
        items-center
        justify-center
        px-4
        py-8
        sm:px-6
    "
>

    <div class="w-full max-w-xl">


        <div
            class="
                w-full
                bg-surface-container-lowest
                rounded-xl
                shadow-xl
                overflow-hidden
            "
        >


            {{-- FRANJA SUPERIOR --}}
            <div
                class="
                    h-2
                    w-full
                    bg-gradient-to-r
                    from-secondary
                    via-primary
                    to-primary-container
                "
            ></div>


            <div class="p-6 sm:p-10">


                {{-- LOGO --}}
                <div
                    class="
                        flex
                        flex-col
                        items-center
                        text-center
                        mb-8
                    "
                >

                    <div
                        class="
                            w-32
                            h-32
                            mb-4
                            flex
                            items-center
                            justify-center
                        "
                    >

                        <img
                            src="{{ asset('images/logo.png') }}"
                            alt="DHACER ECO LIMP"
                            class="w-full h-full object-contain"
                        >

                    </div>


                    <div class="inline-flex items-center gap-2">

                        <h1
                            class="
                                text-2xl
                                font-semibold
                                text-on-surface
                                tracking-tight
                            "
                        >
                            DHACER ECO LIMP
                        </h1>


                        <span
                            class="
                                text-xs
                                font-semibold
                                px-2
                                py-1
                                rounded-full
                                bg-secondary-container
                                text-on-secondary-container
                            "
                        >
                            S.R.L.
                        </span>

                    </div>


                    <p
                        class="
                            text-sm
                            text-on-surface-variant
                            mt-2
                        "
                    >
                        Sistema Web de Gestión
                    </p>


                    <div
                        class="
                            w-12
                            h-0.5
                            bg-surface-variant
                            rounded-full
                            mt-4
                        "
                    ></div>

                </div>



                {{-- TÍTULO --}}
                <div class="mb-6">

                    <h2
                        class="
                            text-xl
                            font-semibold
                            text-on-surface
                        "
                    >
                        Iniciar sesión
                    </h2>

                    <p
                        class="
                            text-sm
                            text-on-surface-variant
                            mt-1
                        "
                    >
                        Ingresa tus credenciales para acceder al sistema.
                    </p>

                </div>



                {{-- MENSAJE DE ÉXITO --}}
                @if(session('success'))

                    <div
                        class="
                            mb-5
                            flex
                            items-start
                            gap-3
                            p-4
                            rounded-lg
                            bg-on-primary-container
                        "
                    >

                        <span
                            class="
                                material-symbols-outlined
                                text-primary
                                shrink-0
                            "
                        >
                            check_circle
                        </span>


                        <div>

                            <p
                                class="
                                    text-sm
                                    font-semibold
                                    text-primary
                                "
                            >
                                Operación completada
                            </p>

                            <p
                                class="
                                    text-sm
                                    text-on-surface
                                    mt-0.5
                                "
                            >
                                {{ session('success') }}
                            </p>

                        </div>

                    </div>

                @endif



                {{-- ERRORES --}}
                @if($errors->any())

                    <div
                        class="
                            mb-5
                            flex
                            items-start
                            gap-3
                            p-4
                            rounded-lg
                            bg-error-container
                        "
                    >

                        <span
                            class="
                                material-symbols-outlined
                                text-error
                                shrink-0
                            "
                        >
                            error
                        </span>


                        <div>

                            <p
                                class="
                                    text-sm
                                    font-semibold
                                    text-error
                                "
                            >
                                No se pudo iniciar sesión
                            </p>


                            @foreach($errors->all() as $error)

                                <p
                                    class="
                                        text-sm
                                        text-on-error-container
                                        mt-1
                                    "
                                >
                                    {{ $error }}
                                </p>

                            @endforeach

                        </div>

                    </div>

                @endif



                {{-- FORMULARIO --}}
                <form
                    method="POST"
                    action="{{ route('login.authenticate') }}"
                    class="space-y-5"
                >

                    @csrf



                    {{-- CORREO --}}
                    <div class="flex flex-col gap-2">

                        <label
                            for="email"
                            class="
                                text-sm
                                font-medium
                                text-on-surface-variant
                            "
                        >
                            Correo electrónico
                        </label>


                        <div class="relative flex items-center">

                            <div
                                class="
                                    absolute
                                    left-3.5
                                    flex
                                    items-center
                                    pointer-events-none
                                    text-on-surface-variant
                                "
                            >

                                <span
                                    class="
                                        material-symbols-outlined
                                        text-xl
                                    "
                                >
                                    mail
                                </span>

                            </div>


                            <input
                                type="email"
                                id="email"
                                name="email"

                                value="{{ old('email') }}"

                                placeholder="usuario@ejemplo.com"

                                autocomplete="email"

                                autofocus
                                required

                                class="
                                    w-full
                                    h-12
                                    pl-11
                                    pr-4
                                    rounded-lg
                                    border
                                    bg-surface-container-lowest
                                    text-on-surface
                                    placeholder:text-outline
                                    focus:outline-none
                                    focus:ring-2
                                    focus:ring-primary
                                    focus:border-primary
                                    transition-all

                                    @error('email')
                                        border-error
                                        ring-1
                                        ring-error
                                    @else
                                        border-outline-variant
                                    @enderror
                                "
                            >

                        </div>


                        @error('email')

                            <p
                                class="
                                    text-sm
                                    text-error
                                    flex
                                    items-center
                                    gap-1
                                "
                            >

                                <span
                                    class="
                                        material-symbols-outlined
                                        text-base
                                    "
                                >
                                    error
                                </span>

                                {{ $message }}

                            </p>

                        @enderror

                    </div>



                    {{-- CONTRASEÑA --}}
                    <div class="flex flex-col gap-2">

                        <label
                            for="password"
                            class="
                                text-sm
                                font-medium
                                text-on-surface-variant
                            "
                        >
                            Contraseña
                        </label>


                        <div class="relative flex items-center">

                            <div
                                class="
                                    absolute
                                    left-3.5
                                    flex
                                    items-center
                                    pointer-events-none
                                    text-on-surface-variant
                                "
                            >

                                <span
                                    class="
                                        material-symbols-outlined
                                        text-xl
                                    "
                                >
                                    key
                                </span>

                            </div>


                            <input
                                type="password"
                                id="password"
                                name="password"

                                placeholder="••••••••"

                                autocomplete="current-password"

                                required

                                class="
                                    w-full
                                    h-12
                                    pl-11
                                    pr-12
                                    rounded-lg
                                    border
                                    bg-surface-container-lowest
                                    text-on-surface
                                    placeholder:text-outline
                                    focus:outline-none
                                    focus:ring-2
                                    focus:ring-primary
                                    focus:border-primary
                                    transition-all

                                    @error('password')
                                        border-error
                                        ring-1
                                        ring-error
                                    @else
                                        border-outline-variant
                                    @enderror
                                "
                            >


                            <button
                                type="button"
                                onclick="togglePasswordVisibility()"

                                class="
                                    absolute
                                    right-3
                                    p-1
                                    rounded-md
                                    text-on-surface-variant
                                    hover:text-on-surface
                                    hover:bg-surface-container
                                    transition-colors
                                "

                                title="Mostrar u ocultar contraseña"
                            >

                                <span
                                    id="eye-icon"
                                    class="
                                        material-symbols-outlined
                                        text-xl
                                    "
                                >
                                    visibility
                                </span>

                            </button>

                        </div>


                        @error('password')

                            <p
                                class="
                                    text-sm
                                    text-error
                                    flex
                                    items-center
                                    gap-1
                                "
                            >

                                <span
                                    class="
                                        material-symbols-outlined
                                        text-base
                                    "
                                >
                                    error
                                </span>

                                {{ $message }}

                            </p>

                        @enderror

                    </div>



                    {{-- RECORDAR --}}
                    <div
                        class="
                            flex
                            flex-col
                            sm:flex-row
                            sm:items-center
                            sm:justify-between
                            gap-3
                        "
                    >

                        <label
                            class="
                                flex
                                items-center
                                gap-2
                                cursor-pointer
                                select-none
                            "
                        >

                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                value="1"

                                {{ old('remember') ? 'checked' : '' }}

                                class="
                                    w-4
                                    h-4
                                    rounded
                                    cursor-pointer
                                    accent-primary
                                "
                            >


                            <span
                                class="
                                    text-sm
                                    text-on-surface-variant
                                "
                            >
                                Recordarme
                            </span>

                        </label>


                        <div
                            class="
                                inline-flex
                                items-center
                                gap-1.5
                                px-2.5
                                py-1.5
                                rounded-lg
                                bg-surface-container
                                text-on-surface-variant
                                self-start
                                sm:self-auto
                            "
                        >

                            <span
                                class="
                                    material-symbols-outlined
                                    text-base
                                    text-primary
                                "
                            >
                                verified_user
                            </span>

                            <span class="text-xs font-medium">
                                Solo personal autorizado
                            </span>

                        </div>

                    </div>



                    {{-- BOTÓN --}}
                    <div class="pt-2">

                        <button
                            type="submit"

                            class="
                                w-full
                                h-12
                                rounded-lg
                                bg-primary
                                hover:bg-primary-container
                                text-on-primary
                                font-semibold
                                flex
                                items-center
                                justify-center
                                gap-2.5
                                shadow-md
                                hover:shadow-lg
                                transition-all
                                active:scale-[0.99]
                                focus:outline-none
                                focus:ring-2
                                focus:ring-offset-2
                                focus:ring-primary
                            "
                        >

                            <span>
                                Iniciar sesión
                            </span>

                            <span
                                class="
                                    material-symbols-outlined
                                    text-xl
                                "
                            >
                                login
                            </span>

                        </button>

                    </div>

                </form>



                {{-- INFORMACIÓN --}}
                <div
                    class="
                        mt-8
                        bg-surface-container-low
                        rounded-xl
                        p-4
                        flex
                        items-start
                        gap-3
                    "
                >

                    <div
                        class="
                            p-1
                            rounded-full
                            bg-surface-container
                            text-primary
                            shrink-0
                        "
                    >

                        <span
                            class="
                                material-symbols-outlined
                                text-lg
                            "
                        >
                            info
                        </span>

                    </div>


                    <div>

                        <p
                            class="
                                text-xs
                                font-semibold
                                uppercase
                                tracking-wider
                                text-on-surface
                            "
                        >
                            Gestión de accesos
                        </p>

                        <p
                            class="
                                text-sm
                                text-on-surface-variant
                                mt-1
                                leading-relaxed
                            "
                        >
                            Las cuentas son creadas y administradas
                            exclusivamente por el Administrador del sistema.
                        </p>

                    </div>

                </div>



                {{-- SEGURIDAD --}}
                <div
                    class="
                        mt-6
                        flex
                        items-center
                        justify-center
                        text-on-surface-variant
                        text-xs
                    "
                >

                    <span class="flex items-center gap-2">

                        <span
                            class="
                                material-symbols-outlined
                                text-primary
                                text-base
                            "
                        >
                            lock
                        </span>

                        Acceso restringido a usuarios autorizados

                    </span>

                </div>


            </div>

        </div>



        {{-- FOOTER --}}
        <div class="mt-8 text-center px-4">

            <p
                class="
                    text-sm
                    text-on-surface-variant
                "
            >
                © 2026

                <strong class="text-on-surface">
                    DHACER ECO LIMP S.R.L.
                </strong>

                — Sistema Web de Gestión.
            </p>


            <p
                class="
                    text-xs
                    text-outline
                    mt-1
                "
            >
                Proyecto Universitario de Ingeniería de Sistemas
            </p>

        </div>


    </div>

</main>



<script>

    function togglePasswordVisibility() {

        const passwordInput =
            document.getElementById('password');

        const eyeIcon =
            document.getElementById('eye-icon');


        if (passwordInput.type === 'password') {

            passwordInput.type = 'text';

            eyeIcon.textContent = 'visibility_off';

        } else {

            passwordInput.type = 'password';

            eyeIcon.textContent = 'visibility';

        }

    }

</script>


</body>

</html>