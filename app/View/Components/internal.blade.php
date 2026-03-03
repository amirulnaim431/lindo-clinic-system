<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Lindo Clinic') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900">
<div class="min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-72 bg-white border-r border-slate-200 hidden lg:flex lg:flex-col">
        <div class="px-6 py-5 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-slate-900 text-white grid place-items-center font-bold">
                    L
                </div>
                <div>
                    <div class="font-semibold leading-tight">Lindo Clinic</div>
                    <div class="text-xs text-slate-500">Internal System</div>
                </div>
            </div>
        </div>

        <nav class="px-3 py-4 space-y-1">
            @php
                $is = fn($name) => request()->routeIs($name);
                $link = function($route, $label, $active) {
                    $base = "flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition";
                    return $active
                        ? $base." bg-slate-900 text-white"
                        : $base." text-slate-700 hover:bg-slate-100";
                };
            @endphp

            <a href="{{ route('app.dashboard') }}" class="{{ $link('app.dashboard', 'Dashboard', $is('app.dashboard')) }}">
                <span class="h-2 w-2 rounded-full {{ $is('app.dashboard') ? 'bg-white' : 'bg-slate-400' }}"></span>
                Dashboard
            </a>

            <a href="{{ route('app.appointments.index') }}" class="{{ $link('app.appointments.index', 'Appointments', $is('app.appointments.*')) }}">
                <span class="h-2 w-2 rounded-full {{ $is('app.appointments.*') ? 'bg-white' : 'bg-slate-400' }}"></span>
                Appointments
            </a>

            <a href="{{ route('app.staff.index') }}" class="{{ $link('app.staff.*', 'Staff', $is('app.staff.*')) }}">
                <span class="h-2 w-2 rounded-full {{ $is('app.staff.*') ? 'bg-white' : 'bg-slate-400' }}"></span>
                Staff
            </a>

            <div class="pt-3 mt-3 border-t border-slate-200">
                <div class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Coming next
                </div>
                <div class="px-3 py-2 rounded-xl text-sm text-slate-400">
                    Calendar • Customers • Reports
                </div>
            </div>
        </nav>

        <div class="mt-auto px-6 py-4 border-t border-slate-200">
            <div class="text-xs text-slate-500">Signed in as</div>
            <div class="text-sm font-medium">{{ auth()->user()->name }}</div>
            <div class="text-xs text-slate-500">{{ auth()->user()->role }}</div>
        </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col">
        <!-- Topbar -->
        <header class="bg-white border-b border-slate-200">
            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold">{{ $title ?? 'Internal' }}</div>
                    @if(!empty($subtitle))
                        <div class="text-xs text-slate-500">{{ $subtitle }}</div>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('profile.edit') }}" class="text-sm text-slate-700 hover:underline">
                        Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="px-3 py-2 rounded-xl border border-slate-200 text-sm hover:bg-slate-50">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-6">
            {{ $slot }}
        </main>
    </div>

</div>
</body>
</html>