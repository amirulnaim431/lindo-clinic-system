<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Lindo Clinic') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell">
    <div class="app-frame">
        <aside class="app-sidebar">
            <div class="app-sidebar__brand">
                <a href="{{ route('app.dashboard') }}" class="app-brand">
                    <div class="app-brand__logo">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L12 3l9 4.5M4.5 8.25V16.5L12 21l7.5-4.5V8.25M12 21V12M3 7.5l9 4.5 9-4.5" />
                        </svg>
                    </div>
                    <div>
                        <div class="app-brand__eyebrow">Lindo Clinic</div>
                        <div class="app-brand__title">Staff Operations</div>
                    </div>
                </a>
            </div>

            <nav class="app-sidebar__nav">
                <a href="{{ route('app.dashboard') }}" class="app-nav-link {{ request()->routeIs('app.dashboard') ? 'is-active' : '' }}">
                    Dashboard
                </a>

                <a href="{{ route('app.appointments.index') }}" class="app-nav-link {{ request()->routeIs('app.appointments.*') ? 'is-active' : '' }}">
                    Appointments
                </a>

                <a href="{{ route('app.staff.index') }}" class="app-nav-link {{ request()->routeIs('app.staff.*') ? 'is-active' : '' }}">
                    Staff
                </a>

                <a href="{{ route('app.calendar') }}" class="app-nav-link {{ request()->routeIs('app.calendar') ? 'is-active' : '' }}">
                    Calendar
                </a>
            </nav>

            <div class="app-sidebar__footer">
                <div class="app-user-card">
                    <div class="app-user-card__label">Signed in as</div>
                    <div class="app-user-card__name">{{ Auth::user()->name }}</div>
                    <div class="app-user-card__email">{{ Auth::user()->email }}</div>

                    <div class="app-user-card__actions">
                        <a href="{{ route('profile.edit') }}" class="btn btn-secondary w-full">
                            Profile
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-full">
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <div>
                    <div class="app-topbar__eyebrow">Lindo Clinic</div>
                    <div class="app-topbar__title">Internal System</div>
                </div>

                <a href="{{ route('profile.edit') }}" class="btn btn-secondary">
                    Profile
                </a>
            </header>

            @isset($header)
                <div class="app-header-slot">
                    {{ $header }}
                </div>
            @endisset

            <main class="app-content">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>