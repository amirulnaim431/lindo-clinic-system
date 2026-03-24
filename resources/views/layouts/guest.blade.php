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
                        <span class="auth-brand__tag">Secure access for live front desk, admin, and clinic operations.</span>
                    </span>
                </a>

                <div class="auth-copy">
                    <div class="auth-kicker">Lindo workspace</div>
                    <h1 class="auth-title">Elegant, live-ready access for the clinic team.</h1>
                    <p class="auth-subtitle">
                        The internal system keeps daily patient operations clear, calm, and polished from login through every operational screen.
                    </p>

                    <div class="auth-points">
                        <div class="auth-point">
                            <span class="auth-point__dot"></span>
                            <div>
                                <div class="auth-point__title">Reception ready</div>
                                <div class="auth-point__body">Appointments, customer records, and staff coordination stay readable and fast under pressure.</div>
                            </div>
                        </div>
                        <div class="auth-point">
                            <span class="auth-point__dot"></span>
                            <div>
                                <div class="auth-point__title">Brand aligned</div>
                                <div class="auth-point__body">A white workspace with dusty pink accents keeps the internal system aligned with Lindo's updated direction.</div>
                            </div>
                        </div>
                        <div class="auth-point">
                            <span class="auth-point__dot"></span>
                            <div>
                                <div class="auth-point__title">Asset ready</div>
                                <div class="auth-point__body">Drop optional visuals into `public/assets/branding/` or `public/assets/clinic/` without changing layout code.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-image-slot">
                @php
                    $authBrandImage = public_path('assets/branding/auth-side.jpg');
                    $authClinicImage = public_path('assets/clinic/auth-side.jpg');
                    $authImage = file_exists($authBrandImage)
                        ? asset('assets/branding/auth-side.jpg')
                        : (file_exists($authClinicImage) ? asset('assets/clinic/auth-side.jpg') : null);
                @endphp

                @if ($authImage)
                    <img src="{{ $authImage }}" alt="Lindo Clinic interior branding">
                @else
                    <div class="auth-image-slot__placeholder">
                        <div class="auth-image-slot__frame">
                            <div class="page-kicker">Optional brand image</div>
                            <div class="panel-title-display">Drop a branded visual here later.</div>
                            <p class="panel-subtitle">
                                Supported placeholder paths: `public/assets/branding/auth-side.jpg` or `public/assets/clinic/auth-side.jpg`.
                            </p>
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
