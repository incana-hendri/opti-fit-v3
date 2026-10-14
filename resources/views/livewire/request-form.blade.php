<div class="space-y-6">
    <x-page-header :title="$changing ? 'Change package or members' : 'Choose your package'"
                   :subtitle="$changing ? 'Your current package carries on until the end of the month. A change starts on the 1st of next month.' : 'Pick a monthly package and who it covers.'" />

    @if (! $hasEmergencyContact)
        <x-card>
            <p class="text-lg font-semibold">Add an emergency contact first</p>
            <p class="mt-1 text-muted">We need someone to call if something happens during a class before we can set up your package.</p>
            <x-button href="{{ route('family') }}" class="mt-5">Add emergency contact</x-button>
        </x-card>
    @elseif ($pending)
        <x-card>
            <p class="text-lg font-semibold">Your request is waiting for approval</p>
            <p class="mt-1 text-muted">You asked for {{ $pending->package->name }}. We'll email you once it's approved, and you can only have one request at a time.</p>
            <x-button href="{{ route('membership') }}" class="mt-5">View my request</x-button>
        </x-card>
    @elseif ($packages->isEmpty())
        <x-empty-state title="No packages available" icon="heroicon-o-tag">
            We're finalising this month's packages. Please check back shortly.
        </x-empty-state>
    @else
        <form wire:submit="send" class="space-y-8">
            @error('form')
                <p role="alert" class="rounded-xl border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger">{{ $message }}</p>
            @enderror

            <fieldset class="space-y-3">
                <legend class="mb-2 text-lg font-semibold">1. Package</legend>
                @foreach ($packages as $option)
                    <x-radio-card wire:key="package-{{ $option->id }}" name="packageId" :value="$option->id" :title="$option->name"
                                  :aside="App\Support\Money::format($option->priceFor(max(1, $count))).' / month'"
                                  wire:model.live="packageId">
                        {{ $option->credits_per_member }} credits per member each month.{{ $option->description ? ' '.$option->description : '' }}
                    </x-radio-card>
                @endforeach
                @error('packageId') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset class="space-y-1">
                <legend class="mb-2 text-lg font-semibold">2. Who is it for?</legend>
                @foreach ($members as $member)
                    <x-checkbox wire:key="member-{{ $member->id }}" :id="'member-'.$member->id" :value="$member->id" wire:model.live="memberIds"
                                :label="$member->fullName()" />
                @endforeach
                @error('memberIds') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            </fieldset>

            <x-card aria-live="polite" class="border-brand/40">
                @if (! $package)
                    <p class="text-muted">Choose a package to see what it costs.</p>
                @elseif ($count === 0)
                    <p class="text-muted">Choose at least one family member to see what it costs.</p>
                @else
                    <p class="text-sm text-muted">Your monthly package</p>
                    <p class="mt-1">
                        <span class="text-2xl font-semibold text-brand tabular-nums">{{ $credits }} credits / month</span>
                        <span class="text-muted"> · </span>
                        <span class="text-xl font-semibold tabular-nums whitespace-nowrap">{{ App\Support\Money::format($priceCents) }} / month</span>
                    </p>
                    <p class="mt-1 text-sm text-muted">{{ $package->name }} for {{ $count }} {{ Illuminate\Support\Str::plural('member', $count) }}.</p>
                @endif
            </x-card>

            <div class="space-y-1.5">
                <label for="note" class="block text-sm font-medium text-muted">Anything we should know? <span class="text-subtle">(optional)</span></label>
                <textarea id="note" wire:model="note" rows="3" maxlength="500"
                          class="block w-full rounded-xl border border-line bg-raised px-4 py-3 text-base text-white placeholder:text-subtle focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40"></textarea>
                @error('note') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-3">
                <p class="text-sm text-muted">We'll confirm and send payment details by email.</p>
                <x-button type="submit" block wire:loading.attr="disabled" wire:target="send">Send request</x-button>
                <x-button href="{{ route('home') }}" variant="ghost" block>I'll do this later</x-button>
            </div>
        </form>
    @endif
</div>
