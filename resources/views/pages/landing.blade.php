{{-- Placeholder hero. The full landing page is built in Milestone 4 (docs/04 § Landing). --}}
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
                <x-button href="{{ url('/register') }}" size="lg" block class="sm:w-auto">Join OptiFit</x-button>
                <x-button href="{{ url('/timetable') }}" variant="secondary" size="lg" block class="sm:w-auto">See timetable</x-button>
            </div>
        </div>
    </section>
</x-layouts.public>
