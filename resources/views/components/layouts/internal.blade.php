@props([
    'title' => null,
    'subtitle' => null,
])

@vite(['resources/css/app.css', 'resources/js/app.js'])

@php
    $is = fn (string $name) => request()->routeIs($name);

    $r = function (string $name, array $params = [], string $fallback = '#') {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : $fallback;
    };

    $customersNavActive = request()->routeIs('app.customers.*') || request()->routeIs('app.customers.import.*');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Lindo Clinic' }}</title>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="flex min-h-screen">
        <aside class="hidden w-72 shrink-0 bg-slate-950 text-slate-100 lg:flex lg:flex-col">
            <div class="border-b border-slate-800 px-6 py-6">
                <div class="text-xl font-semibold tracking-tight">Lindo Clinic</div>
                <div class="mt-1 text-sm text-slate-400">Internal System</div>
                <p class="mt-4 text-sm leading-6 text-slate-400">
                    Operations, customer CRM, staff and clinic workflow
                </p>
            </div>

            <nav class="flex-1 space-y-8 px-4 py-6">
                <div>
                    <div class="px-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                        Navigation
                    </div>

                    <div class="mt-3 space-y-1">
                        <a href="{{ $r('dashboard') }}"
                           class="{{ $is('dashboard') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} flex items-center rounded-2xl px-3 py-2.5 text-sm font-medium transition">
                            Dashboard
                        </a>

                        <a href="{{ $r('app.calendar') }}"
                           class="{{ $is('app.calendar') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} flex items-center rounded-2xl px-3 py-2.5 text-sm font-medium transition">
                            Calendar
                        </a>

                        <a href="{{ $r('app.appointments.index') }}"
                           class="{{ $is('app.appointments.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} flex items-center rounded-2xl px-3 py-2.5 text-sm font-medium transition">
                            Appointments
                        </a>
                    </div>
                </div>

                <div>
                    <div class="px-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                        Customers
                    </div>

                    <div class="mt-3 space-y-1">
                        <a href="{{ $r('app.customers.index') }}"
                           class="{{ $customersNavActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} flex items-center justify-between rounded-2xl px-3 py-2.5 text-sm font-medium transition">
                            <span>Customer List</span>
                            <span class="rounded-full bg-slate-700 px-2 py-0.5 text-[11px] text-slate-200">CRM</span>
                        </a>

                        <a href="{{ $r('app.customers.import.index') }}"
                           class="{{ $is('app.customers.import.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} flex items-center rounded-2xl px-3 py-2.5 text-sm font-medium transition">
                            Import Customers
                        </a>
                    </div>
                </div>

                <div>
                    <div class="px-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                        Staff
                    </div>

                    <div class="mt-3 space-y-1">
                        <a href="{{ $r('app.staff.index') }}"
                           class="{{ $is('app.staff.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} flex items-center rounded-2xl px-3 py-2.5 text-sm font-medium transition">
                            Staff
                        </a>
                    </div>
                </div>
            </nav>

            <div class="border-t border-slate-800 px-6 py-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Signed in</div>
                <div class="mt-2 text-sm font-medium text-white">{{ auth()->user()->name ?? 'User' }}</div>
                <div class="text-xs text-slate-400">{{ auth()->user()->email ?? '' }}</div>

                <div class="mt-4 flex items-center gap-2">
                    @if (\Illuminate\Support\Facades\Route::has('profile.edit'))
                        <a href="{{ route('profile.edit') }}"
                           class="rounded-xl border border-slate-700 px-3 py-2 text-xs font-medium text-slate-200 transition hover:bg-slate-900">
                            Profile
                        </a>
                    @endif

                    @if (\Illuminate\Support\Facades\Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-900 transition hover:bg-slate-200">
                                Logout
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </aside>

        <main class="flex-1">
            <div class="border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="mx-auto max-w-[1600px] px-4 py-5 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                                Clinic workspace
                            </div>
                            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                                {{ $title ?? 'Dashboard' }}
                            </h1>

                            @if(!empty($subtitle))
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                                    {{ $subtitle }}
                                </p>
                            @endif
                        </div>

                        <div class="text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
                            Staging {{ now()->format('d M Y, H:i') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>