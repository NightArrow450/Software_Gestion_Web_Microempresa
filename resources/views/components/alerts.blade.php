@if(session('success'))

    <div class="app-alert success">

        <span class="material-symbols-outlined">
            check_circle
        </span>

        {{ session('success') }}

    </div>

@endif


@if(session('error'))

    <div class="app-alert error">

        <span class="material-symbols-outlined">
            error
        </span>

        {{ session('error') }}

    </div>

@endif


@if($errors->any())

    <div class="app-alert error">

        <span class="material-symbols-outlined">
            error
        </span>

        <div>

            <strong>
                Revisa la información ingresada.
            </strong>

            <ul style="margin:8px 0 0 18px;">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    </div>

@endif