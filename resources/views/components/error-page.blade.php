{{-- Branded error page (docs/04 § Empty states, errors, feedback). Reads no database, so 500 and 503 render when MariaDB is down. --}}
@props(['code', 'title', 'home' => '/', 'homeLabel' => 'Back to the home page'])

<x-layouts.base :title="$title" class="flex flex-col">
    <main id="main" class="relative flex flex-1 flex-col items-center justify-center px-4 py-10 text-center">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-40 h-80 bg-brand-glow"></div>

        <a href="{{ url('/') }}" class="relative mb-10" aria-label="OptiFit home">
            <x-logo class="h-9 w-auto" />
        </a>

        <p class="relative text-5xl font-semibold text-brand tabular-nums" aria-hidden="true">{{ $code }}</p>
        <h1 class="relative mt-2 text-2xl">{{ $title }}</h1>
        <p class="relative mt-3 max-w-md text-muted">{{ $slot }}</p>

        <div class="relative mt-8 flex flex-col gap-3 sm:flex-row">
            @isset($action)
                {{ $action }}
            @endisset
            <x-button href="{{ $home }}" :variant="isset($action) ? 'secondary' : 'primary'">{{ $homeLabel }}</x-button>
        </div>
    </main>
</x-layouts.base>
