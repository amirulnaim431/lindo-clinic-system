<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Lindo Clinic') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell">
        <section class="auth-showcase">
            <div>
                <a href="{{ url('/') }}" class="auth-brand">
                    <span class="auth-brand__mark" aria-hidden="true">LC</span>
                    <span>
                        <span class="auth-kicker">Lindo Clinic</span>
                        <span class="auth-brand__name">Clinic Workspace</span>
                    </span>
                </a>

                <div class="auth-copy">
                    <div class="auth-kicker">Lindo workspace</div>
                    <h1 class="auth-title">Elegant, live-ready access for the clinic team.</h1>

                    <div class="auth-points">
                        <div class="auth-point">
                            <span class="auth-point__dot"></span>
                            <div>
                                <div class="auth-point__title">Clear under pressure</div>
                            </div>
                        </div>
                        <div class="auth-point">
                            <span class="auth-point__dot"></span>
                            <div>
                                <div class="auth-point__title">Brand aligned</div>
                            </div>
                        </div>
                        <div class="auth-point">
                            <span class="auth-point__dot"></span>
                            <div>
                                <div class="auth-point__title">Operationally focused</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-image-slot">
                @php
                    $authBrandImage = public_path('assets/branding/sidebar-logo.png');
                    $authImage = file_exists($authBrandImage)
                        ? asset('assets/branding/sidebar-logo.png')
                        : null;
                @endphp

                @if ($authImage)
                    <img src="{{ $authImage }}" alt="Lindo Clinic logo">
                @else
                    <div class="auth-image-slot__placeholder">
                        <div class="auth-image-slot__frame">
                            <div class="page-kicker">Brand image</div>
                            <div class="panel-title-display">Add the login logo here later.</div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                {{ $slot }}
            </div>
        </section>
    </div>
</body>
</html>
