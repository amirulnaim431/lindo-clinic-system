<x-guest-layout>
    <div class="auth-kicker">Secure sign in</div>
    <h2 class="panel-title-display">Access the clinic workspace.</h2>

    <x-auth-session-status class="alert alert-success mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="field-block">
            <label for="email" class="field-label">Email</label>
            <input id="email" class="form-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            <x-input-error :messages="$errors->get('email')" class="field-error" />
        </div>

        <div class="field-block">
            <label for="password" class="field-label">Password</label>
            <input id="password" class="form-input" type="password" name="password" required autocomplete="current-password">
            <x-input-error :messages="$errors->get('password')" class="field-error" />
        </div>

        <label class="btn-row" style="justify-content:flex-start;">
            <input id="remember_me" type="checkbox" name="remember">
            <span class="helper-text">Keep this device signed in</span>
        </label>

        <div class="btn-row" style="justify-content:space-between;">
            @if (Route::has('password.request'))
                <a class="auth-link" href="{{ route('password.request') }}">Forgot your password?</a>
            @endif

            <button type="submit" class="btn btn-primary">Log in</button>
        </div>
    </form>
</x-guest-layout>
