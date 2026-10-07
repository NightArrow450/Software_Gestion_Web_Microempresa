@php
    $userHeader = auth()->user();
@endphp

<header class="topbar">

    <div>

        <div class="muted">
            @yield('breadcrumb', 'Inicio /')
        </div>

        <strong>
            @yield('page-title', 'Panel de Control')
        </strong>

    </div>

    <div class="topbar-user">

        <span class="role-pill">
            Rol:
            {{ $userHeader->role?->nombre ?? 'Sin rol' }}
        </span>

        <div style="text-align:right;">

            <div
                style="
                    font-size:14px;
                    font-weight:700;
                "
            >
                {{ $userHeader->first_name }}
                {{ $userHeader->last_name }}
            </div>

            <div class="muted">
                {{ $userHeader->email }}
            </div>

        </div>

    </div>

</header>
