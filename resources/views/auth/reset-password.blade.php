<x-layouts.auth title="Choose a new password" heading="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input label="Email" name="email" type="email" :value="$request->email" autocomplete="email" required />
        <x-input label="New password" name="password" revealable autocomplete="new-password" required autofocus hint="At least 8 characters." />
        <x-input label="Confirm new password" name="password_confirmation" revealable autocomplete="new-password" required />
        <x-button type="submit" block>Save new password</x-button>
    </form>
</x-layouts.auth>
