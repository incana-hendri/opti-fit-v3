{{-- Home (docs/04 § /home). --}}
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

    <section aria-labelledby="next-up-heading" class="space-y-3">
        <h2 id="next-up-heading" class="text-xl">Next up</h2>
        @forelse ($nextUp as $session)
            <a href="{{ route('bookings') }}" wire:key="next-{{ $session->id }}"
               class="block rounded-card border border-line bg-surface p-4 transition-colors duration-150 hover:bg-raised sm:p-5">
                <p class="text-sm text-muted">{{ $session->starts_at->format('l j M') }}</p>
                <p class="mt-0.5 text-lg font-semibold"><span class="tabular-nums">{{ $session->starts_at->format('H:i') }}</span> · {{ $session->title }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($session->bookings as $booking)
                        <x-member-chip :name="$booking->member->first_name" booked />
                    @endforeach
                </div>
            </a>
        @empty
            <x-card class="text-muted">Nothing booked yet.</x-card>
        @endforelse
    </section>

    <x-button href="{{ route('book') }}" size="lg" block>Book a class</x-button>

    @if ($idleMembers->isNotEmpty())
        <p class="text-center text-sm text-muted">
            {{ $idleMembers->pluck('first_name')->join(', ', ' and ') }} {{ $idleMembers->count() === 1 ? 'has' : 'have' }} no class booked this week.
        </p>
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
