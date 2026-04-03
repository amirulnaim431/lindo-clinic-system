<x-guest-layout>
    <div class="auth-kicker">Reset password</div>
    <h2 class="panel-title-display">Choose a new password.</h2>

    <form method="POST" action="{{ route('password.store') }}" class="auth-form">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field-block">
            <label for="email" class="field-label">Email</label>
            <input id="email" class="form-input" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            <x-input-error :messages="$errors->get('email')" class="field-error" />
        </div>

        <div class="field-block">
            <label for="password" class="field-label">New password</label>
            <input id="password" class="form-input" type="password" name="password" required autocomplete="new-password">
            <x-input-error :messages="$errors->get('password')" class="field-error" />
        </div>

        <div class="field-block">
            <label for="password_confirmation" class="field-label">Confirm password</label>
            <input id="password_confirmation" class="form-input" type="password" name="password_confirmation" required autocomplete="new-password">
            <x-input-error :messages="$errors->get('password_confirmation')" class="field-error" />
        </div>

        <div class="btn-row" style="justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">Reset password</button>
        </div>
    </form>
</x-guest-layout>
