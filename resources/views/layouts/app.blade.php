<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'Sistema Web') | DHACER ECO LIMP
    </title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/stitch.css') }}?v=3"
    >

    @stack('styles')
</head>

<body>

    @include('components.sidebar')

    <div class="app-content">

        @include('components.header')

        <main class="page">

            @include('components.alerts')

            @yield('content')

        </main>

    </div>

    @stack('scripts')

</body>

</html>
