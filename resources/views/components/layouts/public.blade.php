@props(['title' => null])

<x-layouts.base :title="$title" class="flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-full focus:bg-brand focus:px-4 focus:py-2 focus:text-ink">Skip to content</a>

    <header class="border-b border-line bg-night/90 backdrop-blur supports-[backdrop-filter]:bg-night/75 sticky top-0 z-30">
        <div class="mx-auto flex h-16 max-w-5xl items-center gap-4 px-4 lg:px-6">
            <a href="{{ url('/') }}" class="shrink-0" aria-label="OptiFit home">
                <x-logo class="h-7 w-auto sm:h-8" />
            </a>
            <nav aria-label="Main" class="ml-auto flex items-center gap-1 sm:gap-2">
                <a href="{{ url('/timetable') }}" class="hidden rounded-full px-3 py-2 text-sm font-medium text-muted hover:text-white sm:inline-flex">Timetable</a>
                <a href="{{ url('/pricing') }}" class="hidden rounded-full px-3 py-2 text-sm font-medium text-muted hover:text-white sm:inline-flex">Pricing</a>
                @auth
                    <x-button href="{{ auth()->user()->isAdmin() ? url('/admin') : route('home') }}" size="sm">My account</x-button>
                @else
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full px-3 text-sm font-medium text-white hover:text-brand-light">Log in</a>
                    <x-button href="{{ route('register') }}" size="sm">Join</x-button>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-line">
        <div class="mx-auto grid max-w-5xl gap-6 px-4 py-10 text-sm text-muted sm:grid-cols-3 lg:px-6">
            <div class="space-y-1">
                <p class="font-semibold text-white">{{ $settings->studio_name }}</p>
                <p><a class="hover:text-white" href="mailto:{{ $settings->contact_email }}">{{ $settings->contact_email }}</a></p>
                @if ($settings->contact_phone)
                    <p><a class="hover:text-white" href="tel:{{ preg_replace('/[^\d+]/', '', $settings->contact_phone) }}">{{ $settings->contact_phone }}</a></p>
                @endif
            </div>
            <div class="space-y-1">
                <p class="font-semibold text-white">Venue</p>
                <p>Gene Louw Activity Centre<br>Tulip Street, Amanda Glen, Durbanville</p>
            </div>
            <div class="space-y-1 sm:text-right">
                <p class="space-x-4">
                    <a class="hover:text-white" href="{{ url('/privacy') }}">Privacy</a>
                    <a class="hover:text-white" href="{{ url('/terms') }}">Terms</a>
                </p>
                <p class="text-subtle">&copy; {{ now()->year }} OptiFit</p>
            </div>
        </div>
    </footer>
</x-layouts.base>
