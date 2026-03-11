<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>{{ $title ?? 'Lindo Clinic' }}</title>

@vite(['resources/css/app.css','resources/js/app.js'])

</head>

<body class="bg-slate-100 text-slate-900">

<div class="min-h-screen flex">

    {{-- SIDEBAR --}}
    <aside class="w-64 bg-slate-900 text-white flex flex-col">

        <div class="px-6 py-5 border-b border-slate-700">
            <div class="text-lg font-semibold tracking-wide">
                Lindo Clinic
            </div>
            <div class="text-xs text-slate-400">
                Internal System
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 text-sm">

            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800 {{ request()->routeIs('dashboard') ? 'bg-slate-800' : '' }}">
                Dashboard
            </a>

            <a href="{{ route('appointments.index') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800 {{ request()->routeIs('appointments.*') ? 'bg-slate-800' : '' }}">
                Appointments
            </a>

            <a href="{{ route('calendar.index') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800 {{ request()->routeIs('calendar.*') ? 'bg-slate-800' : '' }}">
                Calendar
            </a>

            <a href="{{ route('staff.index') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800 {{ request()->routeIs('staff.*') ? 'bg-slate-800' : '' }}">
                Staff
            </a>

        </nav>

        <div class="border-t border-slate-700 p-4 text-xs text-slate-400">
            {{ auth()->user()->name ?? '' }}
        </div>

    </aside>


    {{-- MAIN AREA --}}
    <div class="flex-1 flex flex-col">

        {{-- TOP BAR --}}
        <header class="bg-white border-b border-slate-200">

            <div class="px-8 py-4 flex items-center justify-between">

                <div>
                    <h1 class="text-lg font-semibold text-slate-800">
                        {{ $title ?? '' }}
                    </h1>

                    @isset($subtitle)
                        <p class="text-sm text-slate-500">
                            {{ $subtitle }}
                        </p>
                    @endisset
                </div>

                <div class="flex items-center gap-4 text-sm">

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-slate-600 hover:text-slate-900">
                            Logout
                        </button>
                    </form>

                </div>

            </div>

        </header>


        {{-- PAGE CONTENT --}}
        <main class="flex-1 p-8">

            {{ $slot }}

        </main>

    </div>

</div>

</body>
</html>