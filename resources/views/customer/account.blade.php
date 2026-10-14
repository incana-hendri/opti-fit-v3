{{-- Account (docs/04 § /account). The profile and password forms post to Fortify. --}}
@php($user = auth()->user())

<x-layouts.app title="Account">
    <div class="space-y-8">
        <x-page-header title="Account" />

        <x-card as="section" aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="text-lg">Profile</h2>
            @if (session('status') === 'profile-information-updated')
                <p class="mt-1 text-sm text-success" role="status">Profile saved.</p>
            @endif
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-5 space-y-5">
                @csrf
                @method('PUT')
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input label="First name" name="first_name" :value="$user->first_name" bag="updateProfileInformation" autocomplete="given-name" />
                    <x-input label="Last name" name="last_name" :value="$user->last_name" bag="updateProfileInformation" autocomplete="family-name" />
                </div>
                <x-input label="Mobile phone" name="phone" type="tel" :value="$user->phone" bag="updateProfileInformation" autocomplete="tel" />
                <x-input label="Email" name="email" type="email" :value="$user->email" bag="updateProfileInformation" autocomplete="email"
                         hint="If you change it, we'll send a link to verify the new address." />
                <x-checkbox name="marketing_opt_in" value="1" :checked="old('marketing_opt_in', $user->marketing_opt_in)" bag="updateProfileInformation"
                            label="Send me news and offers from OptiFit" />
                <x-button type="submit" variant="secondary">Save profile</x-button>
            </form>
        </x-card>

        <x-card as="section" aria-labelledby="password-heading">
            <h2 id="password-heading" class="text-lg">Password</h2>
            @if (session('status') === 'password-updated')
                <p class="mt-1 text-sm text-success" role="status">Password changed.</p>
            @endif
            <form method="POST" action="{{ route('user-password.update') }}" class="mt-5 space-y-5">
                @csrf
                @method('PUT')
                <x-input label="Current password" name="current_password" revealable bag="updatePassword" autocomplete="current-password" />
                <x-input label="New password" name="password" revealable bag="updatePassword" autocomplete="new-password" hint="At least 8 characters." />
                <x-input label="Confirm new password" name="password_confirmation" revealable bag="updatePassword" autocomplete="new-password" />
                <x-button type="submit" variant="secondary">Change password</x-button>
            </form>
        </x-card>

        <x-card as="section" aria-labelledby="deletion-heading">
            <h2 id="deletion-heading" class="text-lg">Delete account</h2>
            @if ($user->deletion_requested_at)
                <p class="mt-1 text-muted">Requested on {{ $user->deletion_requested_at->format('D j M Y') }}. The studio will be in touch.</p>
            @else
                <p class="mt-1 text-muted">Ask the studio to delete your account and your family's personal details.</p>
                <x-button variant="secondary" class="mt-5 text-danger" x-data @click="$dispatch('open-sheet', 'delete-account')">Request account deletion</x-button>

                <x-sheet name="delete-account" title="Request account deletion?">
                    <p class="text-muted">
                        We'll let the studio know. They'll remove your personal details and your family's, and confirm with you by email.
                        Your bookings stay until then.
                    </p>
                    <form method="POST" action="{{ route('account.deletion-request') }}" class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                        @csrf
                        <x-button type="submit" variant="danger" class="sm:flex-1">Request deletion</x-button>
                        <x-button variant="secondary" class="sm:flex-1" @click="$dispatch('close-sheet', 'delete-account')">Keep my account</x-button>
                    </form>
                </x-sheet>
            @endif
        </x-card>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="ghost" block>Log out</x-button>
        </form>
    </div>
</x-layouts.app>
