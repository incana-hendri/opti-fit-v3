<x-layouts.auth title="Log in" heading="Welcome back">
    @if (session('status'))
        <p class="mb-5 rounded-xl bg-success/15 px-4 py-3 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-input label="Email" name="email" type="email" autocomplete="email" required autofocus />
        <x-input label="Password" name="password" revealable autocomplete="current-password" required />

        <div class="flex items-center justify-between gap-4">
            <x-checkbox name="remember" label="Keep me logged in" />
            <a href="{{ route('password.request') }}" class="text-sm text-brand hover:text-brand-light">Forgot password?</a>
        </div>

        <x-button type="submit" block>Log in</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        New to OptiFit? <a href="{{ route('register') }}" class="font-medium text-brand hover:text-brand-light">Join here</a>
    </p>
</x-layouts.auth>
