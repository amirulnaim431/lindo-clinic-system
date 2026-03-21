<x-guest-layout>
    <div class="auth-kicker">Secure confirmation</div>
    <h2 class="panel-title-display">Confirm your password.</h2>
    <p class="panel-subtitle">This action requires a quick password check before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
        @csrf

        <div class="field-block">
            <label for="password" class="field-label">Password</label>
            <input id="password" class="form-input" type="password" name="password" required autocomplete="current-password">
            <x-input-error :messages="$errors->get('password')" class="field-error" />
        </div>

        <div class="btn-row" style="justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">Confirm</button>
        </div>
    </form>
</x-guest-layout>
