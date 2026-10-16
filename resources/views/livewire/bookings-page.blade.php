{{-- /bookings (docs/04 § /bookings): Upcoming grouped by session, and Past with status labels. --}}
@php
    use App\Support\Cutoff;
@endphp

<div class="space-y-6">
    <x-page-header title="Bookings" subtitle="What your family has booked." />

    <div role="tablist" aria-label="Bookings" class="inline-flex rounded-full border border-line bg-surface p-1">
        @foreach (['upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label)
            <button type="button" role="tab" id="tab-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" aria-controls="panel-{{ $key }}"
                    wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        'min-h-11 rounded-full px-6 text-base font-semibold transition-colors duration-150',
                        'bg-brand text-ink' => $tab === $key,
                        'text-muted hover:text-white' => $tab !== $key,
                    ])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'upcoming')
        <div role="tabpanel" id="panel-upcoming" aria-labelledby="tab-upcoming" class="space-y-4">
            @forelse ($upcoming as $session)
                @php($cutoffPassed = Cutoff::hasPassed($session))
                <x-card as="article" wire:key="upcoming-{{ $session->id }}" aria-labelledby="upcoming-title-{{ $session->id }}" class="space-y-3">
                    <div>
                        <p class="text-sm text-muted">{{ $session->starts_at->format('l j M') }}</p>
                        <h2 id="upcoming-title-{{ $session->id }}" class="text-lg">
                            <span class="tabular-nums">{{ $session->starts_at->format('H:i') }}</span> · {{ $session->title }}
                        </h2>
                        <p class="flex items-center gap-1 text-sm text-muted">
                            <x-heroicon-o-map-pin class="size-4 shrink-0" aria-hidden="true" />
                            <span class="truncate">{{ $session->location->name }}</span>
                        </p>
                    </div>

                    <ul class="divide-y divide-line border-y border-line">
                        @foreach ($session->bookings as $booking)
                            <li wire:key="booking-{{ $booking->id }}" class="flex items-center justify-between gap-3 py-1">
                                <x-member-chip :name="$booking->member->first_name" booked />
                                <x-button variant="ghost" size="sm" wire:click="confirmCancel({{ $booking->id }})" aria-label="Cancel {{ $booking->member->first_name }}'s booking">Cancel</x-button>
                            </li>
                        @endforeach
                    </ul>

                    <p class="text-sm {{ $cutoffPassed ? 'text-brand' : 'text-muted' }}">
                        @if ($cutoffPassed)
                            Free cancellation has closed. Cancelling now is a late cancellation and the credit isn't returned.
                        @else
                            Free cancellation until {{ $session->cutoffAt()->format('D j M H:i') }}
                        @endif
                    </p>
                </x-card>
            @empty
                <x-empty-state title="Nothing booked yet" icon="heroicon-o-ticket">
                    Pick a class and your family's places show up here.
                    <x-slot:action>
                        <x-button href="{{ route('book') }}">Book a class</x-button>
                    </x-slot:action>
                </x-empty-state>
            @endforelse
        </div>
    @else
        <div role="tabpanel" id="panel-past" aria-labelledby="tab-past" class="space-y-4">
            @forelse ($past as $session)
                <x-card as="article" wire:key="past-{{ $session->id }}" aria-labelledby="past-title-{{ $session->id }}" class="space-y-3">
                    <div>
                        <p class="text-sm text-muted">{{ $session->starts_at->format('l j M') }}</p>
                        <h2 id="past-title-{{ $session->id }}" class="text-lg">
                            <span class="tabular-nums">{{ $session->starts_at->format('H:i') }}</span> · {{ $session->title }}
                        </h2>
                    </div>
                    <ul class="divide-y divide-line border-t border-line">
                        @foreach ($session->bookings as $booking)
                            @php([$label, $color] = $booking->customerLabel())
                            <li wire:key="past-booking-{{ $booking->id }}" class="flex items-center justify-between gap-3 py-3">
                                <span class="font-medium">{{ $booking->member->first_name }}</span>
                                <x-badge :color="$color">{{ $label }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @empty
                <x-empty-state title="No past bookings" icon="heroicon-o-clock">
                    Classes from the last 60 days show up here.
                </x-empty-state>
            @endforelse
        </div>
    @endif

    @php($sheetTitle = $cancelling ? "Cancel {$cancelling->member->first_name}'s booking?" : 'Cancel booking?')
    <x-sheet name="cancel-booking" :title="$sheetTitle">
        @if ($cancelling)
            @php($late = Cutoff::hasPassed($cancelling->classSession))
            <p class="text-muted">
                {{ $cancelling->classSession->title }}, {{ $cancelling->classSession->starts_at->format('D j M, H:i') }}.
            </p>
            @if ($late)
                <p class="mt-3 rounded-xl border border-brand/40 bg-brand/10 p-3">
                    <strong>Late cancellation.</strong> {{ $cancelling->member->first_name }}'s spot will be freed, but the credit won't be returned.
                </p>
            @elseif ($cancelling->credit_charged)
                <p class="mt-3">Your credit will be returned.</p>
            @endif
            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <x-button variant="danger" class="sm:flex-1" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel">
                    {{ $late ? 'Cancel anyway' : 'Cancel booking' }}
                </x-button>
                <x-button variant="secondary" class="sm:flex-1" @click="$dispatch('close-sheet', 'cancel-booking')">Keep booking</x-button>
            </div>
        @endif
    </x-sheet>
</div>
