<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Lindo Clinic' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root{
            --bg: #f4f7fb;
            --panel: #ffffff;
            --panel-soft: #f8fafc;
            --line: #e2e8f0;
            --line-strong: #cbd5e1;
            --text: #0f172a;
            --muted: #64748b;
            --muted-2: #94a3b8;
            --brand: #0f172a;
            --brand-2: #1e293b;
            --accent: #0ea5e9;
            --success-bg: #ecfdf5;
            --success-bd: #a7f3d0;
            --success-tx: #065f46;
            --warn-bg: #fff7ed;
            --warn-bd: #fdba74;
            --warn-tx: #9a3412;
            --danger-bg: #fff1f2;
            --danger-bd: #fecdd3;
            --danger-tx: #9f1239;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
            --radius-lg: 18px;
            --radius-xl: 24px;
            --sidebar-w: 280px;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body{
            background: var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        a{ color: inherit; text-decoration: none; }

        .app-shell{
            min-height: 100vh;
            display: grid;
            grid-template-columns: var(--sidebar-w) minmax(0, 1fr);
        }

        .app-sidebar{
            background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
            color: #e5eefb;
            border-right: 1px solid rgba(255,255,255,0.06);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: sticky;
            top: 0;
        }

        .app-brand{
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .app-brand__eyebrow{
            font-size: 11px;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #93c5fd;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .app-brand__title{
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
        }

        .app-brand__subtitle{
            margin-top: 6px;
            color: #94a3b8;
            font-size: 13px;
        }

        .app-nav{
            padding: 18px 14px;
            display: grid;
            gap: 8px;
        }

        .app-nav__section{
            padding: 10px 12px 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #64748b;
        }

        .app-nav__link{
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            color: #cbd5e1;
            font-size: 14px;
            font-weight: 600;
            transition: .18s ease;
            border: 1px solid transparent;
        }

        .app-nav__link:hover{
            background: rgba(255,255,255,0.06);
            color: #ffffff;
            border-color: rgba(255,255,255,0.06);
        }

        .app-nav__link.is-active{
            background: #ffffff;
            color: #0f172a;
            box-shadow: var(--shadow-sm);
        }

        .app-nav__dot{
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
            opacity: .7;
            flex: 0 0 auto;
        }

        .app-nav__group{
            display: grid;
            gap: 6px;
        }

        .app-nav__group-head{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            color: #cbd5e1;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid transparent;
            transition: .18s ease;
        }

        .app-nav__group-head.is-active{
            background: rgba(255,255,255,0.06);
            color: #ffffff;
            border-color: rgba(255,255,255,0.06);
        }

        .app-nav__group-badge{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            background: rgba(255,255,255,0.10);
            color: inherit;
        }

        .app-nav__subnav{
            display: grid;
            gap: 6px;
            padding-left: 18px;
            border-left: 1px solid rgba(255,255,255,0.08);
            margin-left: 14px;
        }

        .app-nav__sublink{
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
            transition: .18s ease;
        }

        .app-nav__sublink:hover{
            background: rgba(255,255,255,0.05);
            color: #ffffff;
            border-color: rgba(255,255,255,0.06);
        }

        .app-nav__sublink.is-active{
            background: #ffffff;
            color: #0f172a;
            box-shadow: var(--shadow-sm);
        }

        .app-nav__subdot{
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: currentColor;
            opacity: .7;
            flex: 0 0 auto;
        }

        .app-sidebar__footer{
            margin-top: auto;
            padding: 18px 16px 22px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .user-card{
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 14px;
        }

        .user-card__label{
            color: #94a3b8;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
        }

        .user-card__name{
            margin-top: 8px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
        }

        .user-card__email{
            margin-top: 4px;
            color: #cbd5e1;
            font-size: 12px;
            word-break: break-word;
        }

        .user-card__actions{
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        .user-card__actions a,
        .user-card__actions button{
            appearance: none;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            color: #ffffff;
            border-radius: 12px;
            padding: 9px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .user-card__actions a:hover,
        .user-card__actions button:hover{
            background: rgba(255,255,255,0.12);
        }

        .app-main{
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .app-topbar{
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(244,247,251,.88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--line);
            padding: 22px 32px;
        }

        .app-topbar__row{
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 20px;
        }

        .app-topbar__eyebrow{
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .16em;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .app-topbar__title{
            font-size: 30px;
            line-height: 1.1;
            letter-spacing: -0.03em;
            margin: 0;
            color: var(--text);
            font-weight: 800;
        }

        .app-topbar__subtitle{
            margin-top: 8px;
            color: var(--muted);
            font-size: 14px;
            max-width: 760px;
        }

        .app-topbar__meta{
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--muted);
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
        }

        .app-content{
            padding: 28px 32px 40px;
        }

        .page-wrap{
            max-width: 1440px;
            margin: 0 auto;
        }

        .panel{
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
        }

        .panel__header{
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
        }

        .panel__body{
            padding: 24px;
        }

        .panel__title{
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
            letter-spacing: -0.02em;
            font-weight: 800;
            color: var(--text);
        }

        .panel__subtitle{
            margin-top: 6px;
            color: var(--muted);
            font-size: 14px;
        }

        .btn-row{
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn{
            appearance: none;
            border: 1px solid transparent;
            border-radius: 14px;
            padding: 11px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: .18s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary{
            background: var(--brand);
            border-color: var(--brand);
            color: #ffffff;
        }

        .btn-primary:hover{ background: var(--brand-2); }

        .btn-secondary{
            background: #ffffff;
            border-color: var(--line-strong);
            color: var(--text);
        }

        .btn-secondary:hover{
            background: var(--panel-soft);
        }

        .form-grid{
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 16px;
        }

        .col-12{ grid-column: span 12; }
        .col-6{ grid-column: span 6; }
        .col-4{ grid-column: span 4; }
        .col-3{ grid-column: span 3; }

        .field-label{
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .field-input,
        .field-select{
            width: 100%;
            border: 1px solid var(--line-strong);
            background: #ffffff;
            color: var(--text);
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 14px;
            outline: none;
            box-shadow: none;
        }

        .field-input:focus,
        .field-select:focus{
            border-color: #94a3b8;
            box-shadow: 0 0 0 4px rgba(148,163,184,0.14);
        }

        .stats-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .stat-card{
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
        }

        .stat-card__label{
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-card__value{
            color: var(--text);
            font-size: 34px;
            line-height: 1;
            letter-spacing: -0.04em;
            font-weight: 800;
        }

        .stack{
            display: grid;
            gap: 18px;
        }

        .table-wrap{
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 18px;
        }

        table{
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }

        th{
            text-align: left;
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            background: #f8fafc;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }

        td{
            padding: 16px;
            border-bottom: 1px solid #eef2f7;
            color: var(--text);
            font-size: 14px;
            vertical-align: top;
        }

        tbody tr:last-child td{ border-bottom: none; }

        tbody tr:hover{
            background: #fbfdff;
        }

        .text-muted{
            color: var(--muted);
            font-size: 13px;
        }

        .chip{
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--text);
        }

        .chip--soft{
            background: #f8fafc;
            color: var(--muted);
        }

        .empty-state{
            border: 1px dashed var(--line-strong);
            border-radius: 18px;
            background: #ffffff;
            padding: 36px 20px;
            text-align: center;
            color: var(--muted);
            font-size: 14px;
        }

        .alert{
            border-radius: 16px;
            padding: 14px 16px;
            font-size: 14px;
            font-weight: 600;
        }

        .alert-success{
            background: var(--success-bg);
            border: 1px solid var(--success-bd);
            color: var(--success-tx);
        }

        .alert-error{
            background: var(--danger-bg);
            border: 1px solid var(--danger-bd);
            color: var(--danger-tx);
        }

        @media (max-width: 1200px){
            .stats-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 920px){
            .app-shell{ grid-template-columns: 1fr; }
            .app-sidebar{
                position: relative;
                min-height: auto;
            }
            .app-topbar,
            .app-content{ padding-left: 18px; padding-right: 18px; }
            .app-topbar__row{ flex-direction: column; align-items: start; }
            .form-grid{ grid-template-columns: repeat(1, minmax(0,1fr)); }
            .col-12,.col-6,.col-4,.col-3{ grid-column: span 1; }
        }

        @media (max-width: 640px){
            .stats-grid{ grid-template-columns: 1fr; }
            .app-topbar__title{ font-size: 24px; }
        }
    </style>
</head>
<body>
    @php
        $is = fn (string $name) => request()->routeIs($name);

        $r = function (string $name, array $params = [], string $fallback = '#') {
            return \Illuminate\Support\Facades\Route::has($name)
                ? route($name, $params)
                : $fallback;
        };

        $customersNavActive = request()->routeIs('app.customers.*') || request()->routeIs('app.customers.import.*');
    @endphp

    <div class="app-shell">
        <aside class="app-sidebar">
            <div class="app-brand">
                <div class="app-brand__eyebrow">Lindo Clinic</div>
                <div class="app-brand__title">Internal System</div>
                <div class="app-brand__subtitle">Operations, customer CRM, staff and clinic workflow</div>
            </div>

            <nav class="app-nav">
                <div class="app-nav__section">Navigation</div>

                <a href="{{ $r('app.dashboard') }}"
                   class="app-nav__link {{ $is('app.dashboard') ? 'is-active' : '' }}">
                    <span class="app-nav__dot"></span>
                    <span>Dashboard</span>
                </a>

                <a href="{{ $r('app.appointments.index') }}"
                   class="app-nav__link {{ $is('app.appointments.*') ? 'is-active' : '' }}">
                    <span class="app-nav__dot"></span>
                    <span>Appointments</span>
                </a>

                <a href="{{ $r('app.calendar') }}"
                   class="app-nav__link {{ $is('app.calendar') ? 'is-active' : '' }}">
                    <span class="app-nav__dot"></span>
                    <span>Calendar</span>
                </a>

                <div class="app-nav__group">
                    <div class="app-nav__group-head {{ $customersNavActive ? 'is-active' : '' }}">
                        <span style="display:flex; align-items:center; gap:12px;">
                            <span class="app-nav__dot"></span>
                            <span>Customers</span>
                        </span>
                        <span class="app-nav__group-badge">2</span>
                    </div>

                    <div class="app-nav__subnav">
                        <a href="{{ $r('app.customers.index') }}"
                           class="app-nav__sublink {{ $is('app.customers.index') || $is('app.customers.show') ? 'is-active' : '' }}">
                            <span class="app-nav__subdot"></span>
                            <span>Customer List</span>
                        </a>

                        <a href="{{ $r('app.customers.import.index') }}"
                           class="app-nav__sublink {{ $is('app.customers.import.*') ? 'is-active' : '' }}">
                            <span class="app-nav__subdot"></span>
                            <span>Import Customers</span>
                        </a>
                    </div>
                </div>

                <a href="{{ $r('app.staff.index') }}"
                   class="app-nav__link {{ $is('app.staff.*') ? 'is-active' : '' }}">
                    <span class="app-nav__dot"></span>
                    <span>Staff</span>
                </a>
            </nav>

            <div class="app-sidebar__footer">
                <div class="user-card">
                    <div class="user-card__label">Signed in</div>
                    <div class="user-card__name">{{ auth()->user()->name ?? 'User' }}</div>
                    <div class="user-card__email">{{ auth()->user()->email ?? '' }}</div>

                    <div class="user-card__actions">
                        @if (\Illuminate\Support\Facades\Route::has('profile.edit'))
                            <a href="{{ route('profile.edit') }}">Profile</a>
                        @endif

                        @if (\Illuminate\Support\Facades\Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                                @csrf
                                <button type="submit">Logout</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </aside>

        <main class="app-main">
            <header class="app-topbar">
                <div class="app-topbar__row">
                    <div>
                        <div class="app-topbar__eyebrow">Clinic workspace</div>
                        <h1 class="app-topbar__title">{{ $title ?? 'Dashboard' }}</h1>

                        @isset($subtitle)
                            <div class="app-topbar__subtitle">{{ $subtitle }}</div>
                        @endisset
                    </div>

                    <div class="app-topbar__meta">
                        <span class="badge">Staging</span>
                        <span class="badge">{{ now()->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </header>

            <section class="app-content">
                <div class="page-wrap">
                    {{ $slot }}
                </div>
            </section>
        </main>
    </div>
</body>
</html>