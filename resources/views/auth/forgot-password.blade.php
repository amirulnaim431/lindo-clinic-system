<x-guest-layout>
    <div class="auth-kicker">Password recovery</div>
    <h2 class="panel-title-display">Reset access safely.</h2>

    <x-auth-session-status class="alert alert-success mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <div class="field-block">
            <label for="email" class="field-label">Email</label>
            <input id="email" class="form-input" type="email" name="email" value="{{ old('email') }}" required autofocus>
            <x-input-error :messages="$errors->get('email')" class="field-error" />
        </div>

        <div class="btn-row" style="justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">Email reset link</button>
        </div>
    </form>
</x-guest-layout>
