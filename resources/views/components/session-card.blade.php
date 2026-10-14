{{--
    One class session on /book (docs/04).
    $state: bookable | closed | full | all-booked. Pass an `action` slot to replace the default button.
    $bookedMembers: names of this household's members already booked.
--}}
@props([
    'time', 'title', 'location', 'duration' => 45, 'placesLeft',
    'state' => 'bookable', 'bookedMembers' => [], 'closedNote' => 'Bookings close 8 hours before the class.',
])

@php
    [$badgeColor, $badgeText] = match (true) {
        $placesLeft <= 0 => ['neutral', 'Full'],
        $placesLeft <= 3 => ['orange', $placesLeft.' '.\Illuminate\Support\Str::plural('place', $placesLeft).' left'],
        default => ['success', $placesLeft.' places left'],
    };
@endphp

<x-card {{ $attributes->class('flex gap-3 sm:gap-4') }}>
    <div class="shrink-0">
        <p class="text-xl font-semibold tabular-nums sm:text-2xl">{{ $time }}</p>
        <p class="text-xs text-subtle">{{ $duration }} min</p>
    </div>

    <div class="min-w-0 flex-1 space-y-2">
        <div>
            <h3 class="text-base font-semibold">{{ $title }}</h3>
            <p class="flex items-center gap-1 text-sm text-muted">
                <x-heroicon-o-map-pin class="size-4 shrink-0" aria-hidden="true" />
                <span class="truncate">{{ $location }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <x-badge :color="$badgeColor">{{ $badgeText }}</x-badge>
            @foreach ($bookedMembers as $member)
                <x-member-chip :name="$member" booked />
            @endforeach
        </div>

        {{-- "Full" is already said by the places badge. --}}
        @if (! isset($action) && $state === 'closed')
            <p class="text-sm">
                <span class="font-medium text-muted">Booking closed.</span>
                <span class="text-subtle">{{ $closedNote }}</span>
            </p>
        @elseif (! isset($action) && $state === 'all-booked')
            <p class="text-sm font-medium text-success">All booked</p>
        @endif
    </div>

    @if (isset($action) || $state === 'bookable')
        <div class="flex shrink-0 items-center">
            {{ $action ?? '' }}
            @unless (isset($action))
                <x-button size="sm">Book</x-button>
            @endunless
        </div>
    @endif
</x-card>
