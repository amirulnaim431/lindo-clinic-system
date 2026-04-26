<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Lindo Clinic Internal' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        $is = fn (string $name) => request()->routeIs($name);
        $user = auth()->user();

        $r = function (string $name, array $params = [], string $fallback = '#') {
            return \Illuminate\Support\Facades\Route::has($name)
                ? route($name, $params)
                : $fallback;
        };

        $can = function (string $permission) use ($user) {
            if (! $user || ! method_exists($user, 'hasAppPermission')) {
                return false;
            }

            return $user->hasAppPermission($permission);
        };

        $canDashboard = $can('dashboard.view');
        $canAppointments = $can('appointments.view');
        $canManageAppointments = $can('appointments.manage');
        $canCalendar = $can('calendar.view');
        $appointmentsMode = request()->input('mode') === 'checkin' ? 'checkin' : 'booking';
        $canCustomers = $can('customers.view');
        $canCustomerImport = $can('customers.import');
        $canStaff = $can('staff.view');
        $canHrSchedule = $user && method_exists($user, 'canAccessHrSchedule') ? $user->canAccessHrSchedule() : false;
        $canHrNav = $canHrSchedule || $canStaff;
        $customersNavActive = request()->routeIs('app.customers.*') || request()->routeIs('app.customers.import.*');
        $hrNavActive = request()->routeIs('app.hr.*') || request()->routeIs('app.staff.*');
        $sidebarLogoPath = public_path('assets/branding/sidebar-logo.png');
        $sidebarLogoUrl = file_exists($sidebarLogoPath) ? asset('assets/branding/sidebar-logo.png') : null;
    @endphp

    <div class="app-shell" data-shell>
        <aside class="app-sidebar">
            <div class="app-sidebar__brand">
                <button type="button" class="sidebar-toggle-btn sidebar-toggle-btn--corner" data-sidebar-toggle aria-pressed="false" aria-label="Minimize side panel">
                    <span class="sidebar-toggle-btn__icon" aria-hidden="true">&lt;</span>
                </button>
                <a href="{{ $r('app.dashboard') }}" class="app-brand">
                    @if ($sidebarLogoUrl)
                        <img src="{{ $sidebarLogoUrl }}" alt="Lindo Clinic" class="app-brand__image">
                    @else
                        <span class="app-brand__mark" aria-hidden="true">LC</span>
                        <span>
                            <span class="app-brand__eyebrow">Lindo Clinic</span>
                            <span class="app-brand__title">Clinic Workspace</span>
                        </span>
                    @endif
                </a>
            </div>

            <nav class="app-sidebar__nav">
                <div class="app-nav-section">
                    <div class="app-nav-section__label">Operations</div>
                    <div class="app-nav-list">
                        @if ($canDashboard)
                            <a href="{{ $r('app.dashboard') }}" class="app-nav-link {{ $is('app.dashboard') ? 'is-active' : '' }}">
                                <span class="app-nav-icon" aria-hidden="true">&#8962;</span>
                                <span>Dashboard</span>
                            </a>
                        @endif

                        @if ($canAppointments)
                            <a href="{{ $r('app.appointments.index', ['mode' => 'checkin']) }}" class="app-nav-link {{ $is('app.appointments.*') && $appointmentsMode === 'checkin' ? 'is-active' : '' }}">
                                <span class="app-nav-icon" aria-hidden="true">&#10003;</span>
                                <span>Customer Check-In</span>
                            </a>
                        @endif

                        @if ($canCalendar)
                            <a href="{{ $r('app.calendar') }}" class="app-nav-link {{ $is('app.calendar') ? 'is-active' : '' }}">
                                <span class="app-nav-icon" aria-hidden="true">&#9706;</span>
                                <span>Calendar Board</span>
                            </a>
                        @endif

                        @if ($canAppointments)
                            <a href="{{ $r('app.appointments.index') }}" class="app-nav-link {{ $is('app.appointments.*') && $appointmentsMode === 'booking' ? 'is-active' : '' }}">
                                <span class="app-nav-icon" aria-hidden="true">&#10022;</span>
                                <span>Appointments</span>
                            </a>
                        @endif

                        @if ($canManageAppointments)
                            <a href="{{ $r('app.services.index') }}" class="app-nav-link {{ $is('app.services.*') ? 'is-active' : '' }}">
                                <span class="app-nav-icon" aria-hidden="true">&#9776;</span>
                                <span>Services</span>
                            </a>
                        @endif
                    </div>
                </div>

                @if ($canCustomers || $canCustomerImport)
                    <div class="app-nav-section">
                        <div class="app-nav-group {{ $customersNavActive ? 'is-open' : '' }}">
                            <button
                                type="button"
                                class="app-nav-group-head {{ $customersNavActive ? 'is-active' : '' }}"
                                data-nav-toggle="customers-subnav"
                                aria-expanded="{{ $customersNavActive ? 'true' : 'false' }}"
                            >
                                <span class="app-nav-icon" aria-hidden="true">&#9711;</span>
                                <span>Customers</span>
                                <span class="app-nav-toggle"></span>
                            </button>

                            <div id="customers-subnav" class="app-nav-subnav" @if (! $customersNavActive) hidden @endif>
                                @if ($canCustomers)
                                    <a href="{{ $r('app.customers.index') }}" class="app-nav-sublink {{ $is('app.customers.index') || $is('app.customers.show') || $is('app.customers.edit') ? 'is-active' : '' }}">
                                        <span class="app-nav-subicon" aria-hidden="true">&#8981;</span>
                                        <span>Customer Directory</span>
                                    </a>
                                @endif

                                @if ($canCustomerImport)
                                    <a href="{{ $r('app.customers.import.index') }}" class="app-nav-sublink {{ $is('app.customers.import.*') ? 'is-active' : '' }}">
                                        <span class="app-nav-subicon" aria-hidden="true">&#8658;</span>
                                        <span>Import Customers</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($canHrNav)
                    <div class="app-nav-section">
                        <div class="app-nav-group {{ $hrNavActive ? 'is-open' : '' }}">
                            <button
                                type="button"
                                class="app-nav-group-head {{ $hrNavActive ? 'is-active' : '' }}"
                                data-nav-toggle="hr-subnav"
                                aria-expanded="{{ $hrNavActive ? 'true' : 'false' }}"
                            >
                                <span class="app-nav-icon" aria-hidden="true">&#8984;</span>
                                <span>HR</span>
                                <span class="app-nav-toggle"></span>
                            </button>

                            <div id="hr-subnav" class="app-nav-subnav" @if (! $hrNavActive) hidden @endif>
                                @if ($canHrSchedule)
                                    <a href="{{ $r('app.hr.schedule') }}" class="app-nav-sublink {{ $is('app.hr.schedule') ? 'is-active' : '' }}">
                                        <span class="app-nav-subicon" aria-hidden="true">&#9716;</span>
                                        <span>Staff Schedule</span>
                                    </a>
                                @endif

                                @if ($canStaff)
                                    <a href="{{ $r('app.staff.index') }}" class="app-nav-sublink {{ $is('app.staff.*') ? 'is-active' : '' }}">
                                        <span class="app-nav-subicon" aria-hidden="true">&#8981;</span>
                                        <span>Staff Directory</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </nav>

            <div class="app-sidebar__footer">
                <div class="user-card">
                    <div class="micro-label">Signed in</div>
                    <div class="user-card__name">{{ $user?->name ?? 'User' }}</div>
                    <div class="user-card__email">{{ $user?->email ?? '' }}</div>

                    <div class="user-card__actions">
                        @if (\Illuminate\Support\Facades\Route::has('profile.edit'))
                            <a href="{{ route('profile.edit') }}" class="btn btn-secondary">Profile</a>
                        @endif

                        @if (\Illuminate\Support\Facades\Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">Logout</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </aside>

        <main class="app-main">
            <header class="app-topbar">
                <div class="app-topbar__inner">
                    <div>
                        <div class="page-kicker">Clinic workspace</div>
                        <h1 class="page-title">{{ $title ?? 'Dashboard' }}</h1>
                    </div>

                    <div class="topbar-badges">
                        <span class="topbar-badge">Live operations</span>
                        <span class="topbar-badge">{{ now()->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </header>

            <section class="app-content">
                {{ $slot }}
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const shell = document.querySelector('[data-shell]');
            const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
            const sidebarStorageKey = 'lindo-sidebar-minimized';
            const liveRefreshEnabled = @json((bool) ($liveRefresh ?? false));
            const liveRefreshIntervalMs = 30000;
            let pendingInteraction = false;

            const applySidebarState = function (isMinimized) {
                if (!shell || !sidebarToggle) {
                    return;
                }

                shell.classList.toggle('app-shell--sidebar-minimized', isMinimized);
                sidebarToggle.setAttribute('aria-pressed', isMinimized ? 'true' : 'false');
                sidebarToggle.querySelector('.sidebar-toggle-btn__icon').textContent = isMinimized ? '>' : '<';
            };

            if (shell && sidebarToggle) {
                const savedPreference = window.localStorage.getItem(sidebarStorageKey) === '1';
                applySidebarState(savedPreference);

                sidebarToggle.addEventListener('click', function () {
                    const isMinimized = !shell.classList.contains('app-shell--sidebar-minimized');
                    applySidebarState(isMinimized);
                    window.localStorage.setItem(sidebarStorageKey, isMinimized ? '1' : '0');
                });
            }

            document.querySelectorAll('[data-nav-toggle]').forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    const targetId = toggle.getAttribute('data-nav-toggle');
                    const target = targetId ? document.getElementById(targetId) : null;
                    const group = toggle.closest('.app-nav-group');

                    if (!target || !group) {
                        return;
                    }

                    const isOpen = !target.hasAttribute('hidden');

                    if (isOpen) {
                        target.setAttribute('hidden', 'hidden');
                        toggle.setAttribute('aria-expanded', 'false');
                        group.classList.remove('is-open');
                    } else {
                        target.removeAttribute('hidden');
                        toggle.setAttribute('aria-expanded', 'true');
                        group.classList.add('is-open');
                    }
                });
            });

            const hasActiveInteraction = function () {
                const activeElement = document.activeElement;
                const isEditing = activeElement && (
                    activeElement.matches('input, textarea, select')
                    || activeElement.isContentEditable
                );
                const hasOpenModal = document.querySelector('.modal-shell:not(.hidden)');
                const hasDraggingItem = document.querySelector('.is-dragging');

                return isEditing || !!hasOpenModal || !!hasDraggingItem || pendingInteraction;
            };

            document.addEventListener('submit', function () {
                pendingInteraction = true;
                window.setTimeout(function () {
                    pendingInteraction = false;
                }, 5000);
            });

            document.addEventListener('click', function (event) {
                if (event.target.closest('button, a, [role="button"]')) {
                    pendingInteraction = true;
                    window.setTimeout(function () {
                        pendingInteraction = false;
                    }, 2500);
                }
            });

            if (liveRefreshEnabled) {
                window.setInterval(function () {
                    fetch(window.location.href, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    }).catch(function () {
                        return null;
                    });
                }, 300000);

                window.setInterval(function () {
                    if (document.hidden || hasActiveInteraction()) {
                        return;
                    }

                    window.location.reload();
                }, liveRefreshIntervalMs);
            }
        });
    </script>
</body>
</html>
