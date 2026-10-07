<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Panel de Control | Sistema Web</title>

</head>

<body>

    <h1>Panel de Control</h1>


    <h2>
        Bienvenido,
        {{ auth()->user()->first_name }}
        {{ auth()->user()->last_name }}
    </h2>


    <p>
        <strong>Correo:</strong>
        {{ auth()->user()->email }}
    </p>


    <p>
        <strong>Rol:</strong>
        {{ auth()->user()->role?->nombre }}
    </p>


    <p>
        <strong>Estado:</strong>

        @if(auth()->user()->status)
            Activo
        @else
            Inactivo
        @endif
    </p>


    <hr>

    <form
        method="POST"
        action="{{ route('logout') }}"
    >

        @csrf

        <button type="submit">
            Cerrar sesión
        </button>

    </form>

</body>

</html>