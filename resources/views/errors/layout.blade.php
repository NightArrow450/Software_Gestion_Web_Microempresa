<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') | DHACER ECO LIMP</title>
    <link rel="stylesheet" href="{{ asset('css/stitch.css') }}">
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
</head>
<body class="pagina-error">
    <main class="tarjeta-error">
        <p class="marca-error">DHACER ECO LIMP</p>
        <p class="codigo-error">@yield('codigo')</p>
        <h1>@yield('titulo')</h1>
        <p>@yield('descripcion')</p>
        <a class="btn btn-primary" href="{{ url('/') }}">Volver al inicio</a>
    </main>
</body>
</html>