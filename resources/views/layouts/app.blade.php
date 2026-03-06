<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Lindo Clinic') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen md:flex">
        {{-- Desktop Sidebar --}}
        <aside class="hidden md:flex md:w-72 md:flex-col md:border-r md:border-slate-200 md:bg-white md:fixed md:inset-y-0 md:left-0">
            <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-6">
                <a href="{{ route('app.dashboard') }}" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L12 3l9 4.5M4.5 8.25V16.5L12 21l7.5-4.5V8.25M12 21V12M3 7.5l9 4.5 9-4.5" />
                        </svg>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Lindo Clinic</div>
                        <div class="text-base font-bold text-slate-900">Internal System</div>
                    </div>
                </a>
            </div>

            <div class="px-4 py-5">
                <div class="space-y-1">
                    <a href="{{ route('app.dashboard') }}"
                       class="@if(request()->routeIs('app.dashboard')) bg-slate-900 text-white shadow-sm @else text-slate-700 hover:bg-slate-100 @endif flex items-center rounded-2xl px-4 py-3 text-sm font-medium transition">
                        Dashboard
                    </a>

                    <a href="{{ route('app.appointments.index') }}"
                       class="@if(request()->routeIs('app.appointments.*')) bg-slate-900 text-white shadow-sm @else text-slate-700 hover:bg-slate-100 @endif flex items-center rounded-2xl px-4 py-3 text-sm font-medium transition">
                        Appointments
                    </a>

                    <a href="{{ route('app.staff.index') }}"
                       class="@if(request()->routeIs('app.staff.*')) bg-slate-900 text-white shadow-sm @else text-slate-700 hover:bg-slate-100 @endif flex items-center rounded-2xl px-4 py-3 text-sm font-medium transition">
                        Staff
                    </a>

                    <a href="{{ route('app.calendar') }}"
                       class="@if(request()->routeIs('app.calendar')) bg-slate-900 text-white shadow-sm @else text-slate-700 hover:bg-slate-100 @endif flex items-center rounded-2xl px-4 py-3 text-sm font-medium transition">
                        Calendar
                    </a>
                </div>

                <div class="mt-8">
                    <div class="mb-3 px-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Coming next
                    </div>
                    <div class="px-3 text-sm text-slate-600">
                        Customers · Reports · Settings
                    </div>
                </div>
            </div>

            <div class="mt-auto border-t border-slate-200 p-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Signed in as</div>
                    <div class="mt-2 text-sm font-semibold text-slate-900">{{ Auth::user()->name }}</div>
                    <div class="mt-1 text-sm text-slate-600 break-all">{{ Auth::user()->email }}</div>

                    <div class="mt-4 flex flex-col gap-2">
                        <a href="{{ route('profile.edit') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                            Profile
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main Content --}}
        <div class="flex min-h-screen flex-1 flex-col md:ml-72">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('app.dashboard') }}" class="md:hidden inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L12 3l9 4.5M4.5 8.25V16.5L12 21l7.5-4.5V8.25M12 21V12M3 7.5l9 4.5 9-4.5" />
                            </svg>
                        </a>

                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Lindo Clinic</div>
                            <div class="text-sm font-semibold text-slate-900">Staff Operations</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden sm:block text-right">
                            <div class="text-sm font-semibold text-slate-900">{{ Auth::user()->name }}</div>
                            <div class="text-xs text-slate-500">{{ Auth::user()->email }}</div>
                        </div>

                        <a href="{{ route('profile.edit') }}"
                           class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                            Profile
                        </a>
                    </div>
                </div>
            </header>

            @isset($header)
                <div class="border-b border-slate-200 bg-white px-4 py-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            @endisset

            <main class="flex-1">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>