@if(session('success'))

    <div class="app-alert success" role="status">

        <span class="material-symbols-outlined" aria-hidden="true">
            check_circle
        </span>

        {{ session('success') }}

    </div>

@endif


@if(session('error'))

    <div class="app-alert error" role="alert">

        <span class="material-symbols-outlined" aria-hidden="true">
            error
        </span>

        {{ session('error') }}

    </div>

@endif


@if($errors->any())

    <div class="app-alert error" role="alert">

        <span class="material-symbols-outlined" aria-hidden="true">
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