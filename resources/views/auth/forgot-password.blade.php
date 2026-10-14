<x-layouts.auth title="Forgot password" heading="Forgot your password?">
    <p class="-mt-3 mb-6 text-muted">Enter your email and we'll send you a link to choose a new one.</p>

    @if (session('status'))
        <p class="mb-5 rounded-xl bg-success/15 px-4 py-3 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-input label="Email" name="email" type="email" autocomplete="email" required autofocus />
        <x-button type="submit" block>Send reset link</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        <a href="{{ route('login') }}" class="font-medium text-brand hover:text-brand-light">Back to log in</a>
    </p>
</x-layouts.auth>
