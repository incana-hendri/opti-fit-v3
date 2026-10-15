{{-- Home (docs/04 § /home). The next bookings and the Book button arrive in Milestone 3. --}}
@php
    use App\Support\Period;
@endphp

<div class="space-y-6">
    <x-page-header :title="'Hi '.auth()->user()->first_name" subtitle="Welcome to OptiFit." />

    @if ($hasCredits)
        <x-credit-meter :balance="$balance" :granted="$granted" :used="$used" :month="$month" :expires="$expires" />
    @elseif ($scheduled)
        <x-status-card :title="'Your package starts on 1 '.Period::label($scheduled->starts_period)">
            {{ $scheduled->package->name }} is approved. Your credits arrive on the 1st.
            <x-slot:action>
                <x-button href="{{ route('membership') }}" variant="secondary">View my membership</x-button>
            </x-slot:action>
        </x-status-card>
    @elseif ($pending)
        <x-status-card title="Request pending">
            We'll email you once your {{ $pending->package->name }} package is approved.
            <x-slot:action>
                <x-button href="{{ route('membership') }}" variant="secondary">View my request</x-button>
            </x-slot:action>
        </x-status-card>
    @else
        <x-status-card title="No package yet">
            Choose a monthly package and we'll get your family booked in.
            <x-slot:action>
                <x-button href="{{ route('membership.request') }}">Choose a package</x-button>
            </x-slot:action>
        </x-status-card>
    @endif

    @if ($ending)
        <x-card class="flex flex-wrap items-center justify-between gap-3 border-brand/40">
            <p class="font-medium">Your membership ends on {{ Period::lastDay(Period::current())->format('j M') }}</p>
            <x-button variant="secondary" size="sm" wire:click="undoCancellation">Undo</x-button>
        </x-card>
    @elseif ($scheduled && $hasCredits)
        <x-card class="border-brand/40">
            <p class="font-medium">Your package changes on 1 {{ Period::label($scheduled->starts_period) }}</p>
            <p class="mt-1 text-sm text-muted">{{ $scheduled->package->name }} starts then. Until then your current package carries on.</p>
        </x-card>
    @endif

    @if ($hasCredits && $pending)
        <x-card class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-medium">Request pending: we'll email you once approved.</p>
            <x-button href="{{ route('membership') }}" variant="ghost" size="sm">View</x-button>
        </x-card>
    @endif

    <x-card class="flex items-center gap-4">
        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-brand/15 text-brand">
            <x-heroicon-o-users class="size-6" aria-hidden="true" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ $household->name }}</p>
            <p class="text-sm text-muted">{{ $household->active_members_count }} {{ \Illuminate\Support\Str::plural('member', $household->active_members_count) }}</p>
        </div>
        <x-button href="{{ route('family') }}" variant="ghost" size="sm">Manage</x-button>
    </x-card>
</div>
