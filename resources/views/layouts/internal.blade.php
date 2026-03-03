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

    <style>
        /* Sidebar drawer behavior works even if Tailwind build is outdated */
        #sidebar {
            width: 288px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            transform: translateX(-100%);
            transition: transform 180ms ease-out;
            z-index: 50;
            background: #fff;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        #sidebarOverlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.3);
            z-index: 40;
            display: none;
        }
        /* Desktop pinned */
        @media (min-width: 1024px) {
            #sidebar { position: static; transform: none; }
            #sidebarOverlay { display: none !important; }
            .mobile-only { display: none !important; }
        }

        /* NAV styling done in CSS (not Tailwind) so it never “vanishes” */
        .nav-item{
            display:flex;align-items:center;gap:10px;
            padding:10px 14px;border-radius:12px;
            text-decoration:none;font-size:14px;
            position:relative; user-select:none;
            color:#334155;
        }
        .nav-item:hover{ background:#f1f5f9; }
        .nav-dot{ height:8px;width:8px;border-radius:999px;background:#94a3b8;display:inline-block; }
        .nav-bar{ position:absolute;left:6px;top:50%;transform:translateY(-50%);
            height:22px;width:4px;border-radius:999px;background:transparent; }

        .nav-active{
            background:#0f172a;color:#fff;
            box-shadow:0 1px 1px rgba(0,0,0,.06);
        }
        .nav-active .nav-dot{ background:#fff; }
        .nav-active .nav-bar{ background:#fff; }
    </style>
</head>

<body class="font-sans antialiased bg-slate-50 text-slate-900">
@php
    $is = fn($name) => request()->routeIs($name);

    // Safe route helper to prevent 500 if a route is missing during setup/demo.
    $r = function (string $name, array $params = [], string $fallback = '#') {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : $fallback;
    };
@endphp

<div class="min-h-screen flex">

    <div id="sidebarOverlay" onclick="setSidebar(false)"></div>

    <aside id="sidebar">
        {{-- Brand --}}
        <div class="px-6 py-5 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-slate-900 text-white grid place-items-center font-bold">L</div>
                <div>
                    <div class="font-semibold leading-tight">Lindo Clinic</div>
                    <div class="text-xs text-slate-500">Internal System</div>
                </div>
            </div>

            <button type="button" class="mobile-only p-2 rounded-xl hover:bg-slate-100" onclick="setSidebar(false)">
                ✕
            </button>
        </div>

        {{-- Nav --}}
        <nav class="px-3 py-4 space-y-1">
            <a href="{{ $r('app.dashboard') }}" class="nav-item {{ $is('app.dashboard') ? 'nav-active' : '' }}">
                <span class="nav-bar"></span>
                <span class="nav-dot"></span>
                <span class="font-medium">Dashboard</span>
            </a>

            <a href="{{ $r('app.appointments.index') }}" class="nav-item {{ $is('app.appointments.*') ? 'nav-active' : '' }}">
                <span class="nav-bar"></span>
                <span class="nav-dot"></span>
                <span class="font-medium">Appointments</span>
            </a>

            {{-- ✅ STAFF --}}
            <a href="{{ $r('app.staff.index') }}" class="nav-item {{ $is('app.staff.*') ? 'nav-active' : '' }}">
                <span class="nav-bar"></span>
                <span class="nav-dot"></span>
                <span class="font-medium">Staff</span>
            </a>

            <a href="{{ $r('app.calendar') }}" class="nav-item {{ $is('app.calendar') ? 'nav-active' : '' }}">
                <span class="nav-bar"></span>
                <span class="nav-dot"></span>
                <span class="font-medium">Calendar</span>
            </a>

            <div class="pt-4 mt-4 border-t border-slate-200">
                <div class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Coming next
                </div>
                <div class="px-3 py-2 rounded-xl text-sm text-slate-400">
                    Customers • Reports • Settings
                </div>
            </div>
        </nav>

        {{-- Footer --}}
        <div class="mt-auto px-6 py-4 border-t border-slate-200">
            <div class="text-xs text-slate-500">Signed in as</div>
            <div class="text-sm font-medium">{{ auth()->user()->name ?? '—' }}</div>
            <div class="text-xs text-slate-500">{{ auth()->user()->role ?? '—' }}</div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 flex flex-col">
        <header class="bg-white border-b border-slate-200">
            <div class="px-5 sm:px-8 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button type="button"
                            class="mobile-only px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50"
                            onclick="toggleSidebar()">
                        ☰
                    </button>

                    <div>
                        <div class="text-sm text-slate-500">{{ $subtitle ?? 'Operations' }}</div>
                        <h1 class="text-xl sm:text-2xl font-semibold tracking-tight">
                            {{ $title ?? 'Dashboard' }}
                        </h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ $r('profile.edit', [], '/app/dashboard') }}"
                       class="px-3 py-2 rounded-xl border border-slate-200 text-sm hover:bg-slate-50">
                        Profile
                    </a>

                    <form method="POST" action="{{ $r('logout', [], '/login') }}">
                        @csrf
                        <button class="px-3 py-2 rounded-xl bg-slate-900 text-white text-sm hover:opacity-90">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="px-5 sm:px-8 py-6">
            {{ $slot }}
        </main>
    </div>

</div>

<script>
    function isDesktop() {
        return window.matchMedia('(min-width: 1024px)').matches;
    }

    function setSidebar(open) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (!sidebar || !overlay) return;

        if (isDesktop()) {
            overlay.style.display = 'none';
            sidebar.style.transform = 'none';
            return;
        }

        if (open) {
            sidebar.style.transform = 'translateX(0)';
            overlay.style.display = 'block';
        } else {
            sidebar.style.transform = 'translateX(-100%)';
            overlay.style.display = 'none';
        }
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || isDesktop()) return;

        const open = sidebar.style.transform === 'translateX(0px)' || sidebar.style.transform === 'translateX(0)';
        setSidebar(!open);
    }

    (function init() {
        if (isDesktop()) setSidebar(true);
        else setSidebar(false);

        window.addEventListener('resize', () => {
            if (isDesktop()) setSidebar(true);
            else setSidebar(false);
        });
    })();
</script>
</body>
</html>