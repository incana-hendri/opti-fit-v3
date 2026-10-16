{{--
    Horizontal day picker for /book (docs/04). Each day links to the #day-YYYY-MM-DD section below it.
    $days: list of ['date' => CarbonInterface, 'hasSessions' => bool]
--}}
@props(['days', 'selected' => null])

<nav aria-label="Days" {{ $attributes->class('-mx-4 overflow-x-auto px-4 [scrollbar-width:none] lg:mx-0 lg:px-0') }}>
    <ol class="flex gap-2">
        @foreach ($days as $day)
            @php($isSelected = $selected && $day['date']->isSameDay($selected))
            <li>
                <a href="#day-{{ $day['date']->format('Y-m-d') }}"
                   @if ($isSelected) aria-current="date" @endif
                   @class([
                       'relative flex w-14 flex-col items-center rounded-2xl border py-2 transition-colors duration-150',
                       'border-brand bg-brand text-ink' => $isSelected,
                       'border-line bg-surface hover:bg-raised' => ! $isSelected && $day['hasSessions'],
                       // Days without classes are dimmed with a lighter fill, never with opacity, so the text keeps its contrast.
                       'border-line bg-transparent hover:bg-surface' => ! $isSelected && ! $day['hasSessions'],
                   ])>
                    <span class="text-xs font-medium {{ $isSelected ? '' : 'text-subtle' }}">{{ $day['date']->format('D') }}</span>
                    <span @class(['text-lg tabular-nums', 'font-semibold' => $isSelected || $day['hasSessions'], 'text-subtle' => ! $isSelected && ! $day['hasSessions']])>{{ $day['date']->format('j') }}</span>
                    <span aria-hidden="true" @class([
                        'mt-0.5 size-1.5 rounded-full',
                        'bg-ink' => $isSelected && $day['hasSessions'],
                        'bg-brand' => ! $isSelected && $day['hasSessions'],
                        'bg-transparent' => ! $day['hasSessions'],
                    ])></span>
                    <span class="sr-only">{{ $day['hasSessions'] ? 'has classes' : 'no classes' }}</span>
                </a>
            </li>
        @endforeach
    </ol>
</nav>
