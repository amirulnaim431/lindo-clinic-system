<x-guest-layout>
    <div class="auth-kicker">Verify email</div>
    <h2 class="panel-title-display">Activate your internal access.</h2>
    <p class="panel-subtitle">Before entering the clinic workspace, confirm your email using the verification link we sent.</p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success mt-4">
            A new verification link has been sent to the email address attached to your account.
        </div>
    @endif

    <div class="auth-form">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary w-full">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-secondary w-full">Log out</button>
        </form>
    </div>
</x-guest-layout>
