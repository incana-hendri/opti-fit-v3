@props(['title' => null, 'heading' => null])

<x-layouts.base :title="$title" class="flex flex-col">
    <main id="main" class="relative flex flex-1 flex-col items-center px-4 py-10 sm:justify-center">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-40 h-80 bg-brand-glow"></div>

        <a href="{{ url('/') }}" class="relative mb-8" aria-label="OptiFit home">
            <x-logo tagline class="w-56 sm:w-64" />
        </a>

        <x-card class="relative w-full max-w-md">
            @if ($heading)
                <h1 class="mb-6 text-2xl">{{ $heading }}</h1>
            @endif
            {{ $slot }}
        </x-card>
    </main>
</x-layouts.base>
