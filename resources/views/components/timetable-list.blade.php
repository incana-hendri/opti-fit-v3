{{--
    Read-only timetable for the public pages (docs/04 § /timetable and Landing): sessions grouped by day.
    $sessions: ClassSession models with `location` loaded and withOccupancy().
--}}
@props(['sessions', 'empty' => 'No classes are scheduled yet. Check back soon.', 'level' => 3])

@php($days = $sessions->groupBy(fn ($session) => $session->starts_at->format('Y-m-d')))

@if ($days->isEmpty())
    <x-empty-state title="Nothing scheduled" icon="heroicon-o-calendar-days">
        {{ $empty }}
        @guest
            <x-slot:action>
                <x-button href="{{ route('register') }}" variant="secondary">Join OptiFit</x-button>
            </x-slot:action>
        @elseif (! auth()->user()->isAdmin())
            <x-slot:action>
                <x-button href="{{ route('home') }}" variant="secondary">Back to Home</x-button>
            </x-slot:action>
        @endguest
    </x-empty-state>
@else
    <div {{ $attributes->class('space-y-6') }}>
        @foreach ($days as $date => $daySessions)
            @php($day = \Illuminate\Support\Carbon::parse($date))
            <section aria-labelledby="timetable-{{ $date }}">
                <h{{ $level }} id="timetable-{{ $date }}" class="mb-2 text-base font-semibold">
                    {{ $day->isToday() ? 'Today' : ($day->isTomorrow() ? 'Tomorrow' : $day->format('l')) }}
                    <span class="font-normal text-muted">{{ $day->format('j M') }}</span>
                </h{{ $level }}>
                <ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
                    @foreach ($daySessions as $session)
                        {{-- On a phone the badge drops under the title, so the title keeps its width. --}}
                        <li class="grid grid-cols-[3.5rem_1fr] items-center gap-x-3 gap-y-1.5 px-4 py-3 sm:grid-cols-[3.5rem_1fr_auto] sm:gap-x-4">
                            <p class="text-lg font-semibold tabular-nums sm:row-start-1">{{ $session->starts_at->format('H:i') }}</p>
                            <div class="min-w-0">
                                <p class="font-medium">{{ $session->title }}</p>
                                <p class="truncate text-sm text-muted">{{ $session->location->name }} · {{ $session->duration_minutes }} min</p>
                            </div>
                            <x-places-badge :places-left="$session->placesLeft()" class="col-start-2 justify-self-start sm:col-start-3 sm:row-start-1 sm:justify-self-end" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
