{{--
    Read-only timetable for the public pages (docs/04 § /timetable and Landing): sessions grouped by day.
    $sessions: ClassSession models with `location` loaded and withOccupancy().
--}}
@props(['sessions', 'empty' => 'No classes are scheduled yet. Check back soon.'])

@php($days = $sessions->groupBy(fn ($session) => $session->starts_at->format('Y-m-d')))

@if ($days->isEmpty())
    <x-empty-state title="Nothing scheduled" icon="heroicon-o-calendar-days">{{ $empty }}</x-empty-state>
@else
    <div {{ $attributes->class('space-y-6') }}>
        @foreach ($days as $date => $daySessions)
            @php($day = \Illuminate\Support\Carbon::parse($date))
            <section aria-labelledby="timetable-{{ $date }}">
                <h3 id="timetable-{{ $date }}" class="mb-2 text-base font-semibold">
                    {{ $day->isToday() ? 'Today' : ($day->isTomorrow() ? 'Tomorrow' : $day->format('l')) }}
                    <span class="font-normal text-muted">{{ $day->format('j M') }}</span>
                </h3>
                <ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
                    @foreach ($daySessions as $session)
                        <li class="flex items-center gap-3 px-4 py-3 sm:gap-4">
                            <p class="w-14 shrink-0 text-lg font-semibold tabular-nums">{{ $session->starts_at->format('H:i') }}</p>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $session->title }}</p>
                                <p class="truncate text-sm text-muted">{{ $session->location->name }} · {{ $session->duration_minutes }} min</p>
                            </div>
                            <x-places-badge :places-left="$session->placesLeft()" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
