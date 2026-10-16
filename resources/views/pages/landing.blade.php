{{-- Landing page, in the order of docs/04 § Landing: hero, how it works, packages, this week's timetable, venue. --}}
<x-layouts.public>
    <section class="relative overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-24 h-112 bg-brand-glow"></div>

        <div class="relative mx-auto flex max-w-3xl flex-col items-center px-4 pt-14 pb-16 text-center sm:pt-20 lg:px-6">
            <x-logo tagline class="w-64 sm:w-80" />

            <h1 class="mt-10 text-2xl sm:text-3xl lg:text-4xl">
                Functional fitness for the <span class="text-brand">whole family</span>
            </h1>
            <p class="mt-4 max-w-xl text-lg text-muted">
                45-minute classes in Durbanville where kids and adults train together.
            </p>

            <div class="mt-8 flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <x-button href="{{ route('register') }}" size="lg" block class="sm:w-auto">Join OptiFit</x-button>
                <x-button href="{{ url('/timetable') }}" variant="secondary" size="lg" block class="sm:w-auto">See timetable</x-button>
            </div>
        </div>
    </section>

    <section aria-labelledby="how-heading" class="mx-auto max-w-3xl px-4 pb-14 lg:px-6">
        <h2 id="how-heading" class="mb-4 text-2xl">How it works</h2>
        <ol class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['heroicon-o-user-group', 'Add your family', 'Sign up and tell us who will be training.'],
                ['heroicon-o-tag', 'Choose a monthly package', 'Pick the package that suits your family. We approve it and send the EFT details.'],
                ['heroicon-o-calendar-days', 'Book classes with credits', 'One credit books one person into one class. Credits are shared by the whole family.'],
            ] as $number => [$icon, $step, $text])
                <li>
                    <x-card class="h-full">
                        <span class="grid size-12 place-items-center rounded-full bg-brand/15 text-brand">
                            <x-dynamic-component :component="$icon" class="size-6" aria-hidden="true" />
                        </span>
                        <h3 class="mt-4 text-lg"><span class="text-brand tabular-nums">{{ $number + 1 }}.</span> {{ $step }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $text }}</p>
                    </x-card>
                </li>
            @endforeach
        </ol>
    </section>

    @if ($packages !== [])
        <section aria-labelledby="packages-heading" class="mx-auto max-w-3xl px-4 pb-14 lg:px-6">
            <div class="mb-4 flex items-end justify-between gap-4">
                <h2 id="packages-heading" class="text-2xl">Packages</h2>
                <a href="{{ route('pricing') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand hover:text-brand-light">See all prices</a>
            </div>
            <ul class="grid gap-3 sm:grid-cols-3">
                @foreach ($packages as $package)
                    <li>
                        <x-card as="a" href="{{ route('pricing') }}" class="block h-full transition-colors duration-150 hover:bg-raised">
                            <h3 class="text-lg">{{ $package['name'] }}</h3>
                            <p class="mt-3">
                                <span class="text-sm text-muted">from </span><span class="text-2xl font-semibold text-brand tabular-nums whitespace-nowrap">{{ $package['from'] }}</span><span class="text-sm text-muted">/month</span>
                            </p>
                            <p class="mt-1 text-sm text-muted tabular-nums">{{ $package['credits'] }} credits per member</p>
                        </x-card>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="week-heading" class="mx-auto max-w-3xl px-4 pb-14 lg:px-6">
        <div class="mb-4 flex items-end justify-between gap-4">
            <h2 id="week-heading" class="text-2xl">This week's timetable</h2>
            <a href="{{ url('/timetable') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand hover:text-brand-light">Full timetable</a>
        </div>
        <x-timetable-list :sessions="$sessions" />
    </section>

    @if ($venue)
        <section aria-labelledby="venue-heading" class="mx-auto max-w-3xl px-4 lg:px-6">
            <h2 id="venue-heading" class="mb-4 text-2xl">Where we train</h2>
            <x-card class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-brand/15 text-brand">
                        <x-heroicon-o-map-pin class="size-6" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-lg font-semibold">{{ $venue->name }}</p>
                        <p class="text-muted">{{ $venue->address }}</p>
                    </div>
                </div>
                @if ($venue->map_url)
                    <x-button href="{{ $venue->map_url }}" variant="secondary" target="_blank" rel="noopener">
                        Open in Maps<span class="sr-only"> (opens in a new tab)</span>
                    </x-button>
                @endif
            </x-card>
        </section>
    @endif
</x-layouts.public>
