{{-- /book (docs/04 § /book): day strip, session cards grouped by day, and the booking sheet. --}}
@php
    use App\Support\Cutoff;
    use Illuminate\Support\Str;
@endphp

<div class="space-y-6">
    <x-page-header title="Book a class" :subtitle="$balance.' '.Str::plural('credit', $balance).' left this month'" />

    {{-- Sticky under the header so any day is one tap away while scrolling. --}}
    <div class="sticky top-14 z-20 -mx-4 bg-night/95 px-4 py-2 backdrop-blur lg:top-16 lg:mx-0 lg:px-0">
        <x-day-strip :days="$days" :selected="today()" />
    </div>

    @foreach ($days as $day)
        @php($key = $day['date']->format('Y-m-d'))
        <section id="day-{{ $key }}" aria-labelledby="heading-{{ $key }}" class="scroll-mt-40 space-y-3">
            <h2 id="heading-{{ $key }}" class="text-lg">
                {{ $day['date']->isToday() ? 'Today' : ($day['date']->isTomorrow() ? 'Tomorrow' : $day['date']->format('l')) }}
                <span class="font-normal text-muted">{{ $day['date']->format('j M') }}</span>
            </h2>

            @forelse ($sessionsByDay->get($key, []) as $session)
                @php($state = $states[$session->id])
                @php($note = $state !== 'bookable' ? null : (! $hasPackage ? 'You need an active package before you can book.' : ($balance < 1 ? "You've used all your credits this month." : null)))
                <x-session-card wire:key="session-{{ $session->id }}"
                    :time="$session->starts_at->format('H:i')" :title="$session->title" :location="$session->location->name"
                    :duration="$session->duration_minutes" :places-left="$session->placesLeft()" :state="$state" :note="$note"
                    :booked-members="$booked->get($session->id, collect())->pluck('member.first_name')->all()"
                    :closed-note="'Bookings close '.Cutoff::hoursFor($session).' hours before the class.'">
                    <x-slot:action>
                        @if ($state !== 'bookable')
                        @elseif (! $hasPackage)
                            <x-button href="{{ route('membership.request') }}" variant="secondary" size="sm">Choose a package</x-button>
                        @elseif ($balance < 1)
                            <x-button href="{{ route('membership') }}" variant="secondary" size="sm">No credits left</x-button>
                        @else
                            <x-button size="sm" wire:click="openBook({{ $session->id }})">Book</x-button>
                        @endif
                    </x-slot:action>
                </x-session-card>
            @empty
                <p class="text-sm text-subtle">No classes this day.</p>
            @endforelse
        </section>
    @endforeach

    @php($sheetTitle = $sheet ? 'Book '.$sheet['session']->title : 'Book a class')
    <x-sheet name="book" :title="$sheetTitle">
        @if ($sheet)
            {{-- Inline @php only: Blade can't mix it with a block @php in one file. --}}
            @php($session = $sheet['session'])
            @php($placesLeft = $session->placesLeft())
            @php($tooMany = $sheet['selected'] > $placesLeft)
            @php($tooPricey = $sheet['creditsAfter'] < 0)

            <p class="text-muted">{{ $session->starts_at->format('l j M, H:i') }} · {{ $session->location->name }}</p>

            <fieldset class="mt-4">
                <legend class="mb-1 text-sm font-medium text-muted">Who is coming?</legend>
                @foreach ($sheet['rows'] as $row)
                    @if ($row['booked'])
                        <x-checkbox wire:key="who-{{ $row['member']->id }}" :label="$row['member']->first_name" description="Already booked" checked disabled />
                    @else
                        <x-checkbox wire:key="who-{{ $row['member']->id }}" :label="$row['member']->first_name" wire:model.live="memberIds" value="{{ $row['member']->id }}" />
                    @endif
                @endforeach
            </fieldset>

            <div class="mt-4 space-y-1 rounded-xl bg-raised p-4 text-sm" aria-live="polite">
                @if ($sheet['selected'] === 0)
                    <p class="font-medium">Choose who's coming.</p>
                @else
                    <p class="font-medium tabular-nums">
                        {{ $sheet['selected'] }} {{ Str::plural('credit', $sheet['selected']) }}
                        · you'll have {{ max(0, $sheet['creditsAfter']) }} left
                    </p>
                @endif
                @if ($tooPricey)
                    <p class="text-danger">You only have {{ $balance }} {{ Str::plural('credit', $balance) }}.</p>
                @endif
                @if ($tooMany)
                    <p class="text-danger">{{ $placesLeft <= 0 ? 'This class is full.' : 'Only '.$placesLeft.' '.Str::plural('place', $placesLeft).' left.' }}</p>
                @endif
                <p class="text-muted">Free cancellation until {{ $session->cutoffAt()->format('D j M H:i') }}</p>
            </div>

            @if ($error)
                <p role="alert" class="mt-4 rounded-xl border border-danger/40 bg-danger/10 p-3 text-sm text-danger">{{ $error }}</p>
            @endif

            <x-button block class="mt-5" wire:click="book" wire:loading.attr="disabled" wire:target="book" :disabled="$sheet['selected'] === 0 || $tooMany || $tooPricey">
                Book {{ $sheet['selected'] }} {{ Str::plural('person', $sheet['selected']) }}
            </x-button>
        @endif
    </x-sheet>
</div>
