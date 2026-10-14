{{-- Local-only preview of the auth shell. The real auth views come in Milestone 1. --}}
<x-layouts.auth title="Auth shell" heading="Welcome back">
    <form class="space-y-5" onsubmit="return false">
        <x-input label="Email" name="sg_email" type="email" autocomplete="email" />
        <x-input label="Password" name="sg_password" revealable autocomplete="current-password" />
        <x-button type="submit" block>Log in</x-button>
        <p class="text-center text-sm text-muted">
            <a href="#" class="text-brand hover:text-brand-light">Forgot your password?</a>
        </p>
    </form>
</x-layouts.auth>
