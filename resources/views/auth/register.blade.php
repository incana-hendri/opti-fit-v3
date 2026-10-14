{{-- BR-1.1 to BR-1.4 --}}
<x-layouts.auth title="Join OptiFit" heading="Join OptiFit">
    <p class="-mt-3 mb-6 text-muted">Create the account for your family. You'll add family members next.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input label="First name" name="first_name" autocomplete="given-name" required autofocus />
            <x-input label="Last name" name="last_name" autocomplete="family-name" required />
        </div>
        <x-input label="Email" name="email" type="email" autocomplete="email" required />
        <x-input label="Mobile phone" name="phone" type="tel" autocomplete="tel" placeholder="082 123 4567" required />
        <x-input label="Date of birth" name="date_of_birth" type="date" autocomplete="bday" required
                 max="{{ today()->subYears(18)->toDateString() }}" hint="The account holder must be 18 or older." />
        <x-input label="Password" name="password" revealable autocomplete="new-password" required hint="At least 8 characters." />
        <x-input label="Confirm password" name="password_confirmation" revealable autocomplete="new-password" required />

        <div class="space-y-1 pt-1">
            <x-checkbox name="terms" value="1" :checked="old('terms')" required>
                I accept the <a href="{{ url('/terms') }}" target="_blank" rel="noopener" class="text-brand underline hover:text-brand-light">Terms</a>
                and <a href="{{ url('/privacy') }}" target="_blank" rel="noopener" class="text-brand underline hover:text-brand-light">Privacy Policy</a>
            </x-checkbox>
            <x-checkbox name="marketing_opt_in" value="1" :checked="old('marketing_opt_in')" label="Send me news and offers from OptiFit (optional)" />
        </div>

        <x-button type="submit" block>Create account</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        Already have an account? <a href="{{ route('login') }}" class="font-medium text-brand hover:text-brand-light">Log in</a>
    </p>
</x-layouts.auth>
