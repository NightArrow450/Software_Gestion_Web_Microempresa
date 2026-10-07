<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Iniciar sesión | Sistema Web</title>
</head>

<body>

    <h1>Iniciar sesión</h1>


    @if(session('success'))

        <p>
            {{ session('success') }}
        </p>

    @endif


    @if($errors->any())

        <div>

            @foreach($errors->all() as $error)

                <p>
                    {{ $error }}
                </p>

            @endforeach

        </div>

    @endif


    <form
        method="POST"
        action="{{ route('login.authenticate') }}"
    >

        @csrf


        <div>

            <label for="email">
                Correo electrónico
            </label>

            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
            >

        </div>


        <div>

            <label for="password">
                Contraseña
            </label>

            <input
                type="password"
                id="password"
                name="password"
                required
            >

        </div>


        <div>

            <label>

                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                >

                Recordar sesión

            </label>

        </div>


        <button type="submit">
            Iniciar sesión
        </button>

    </form>

</body>

</html>