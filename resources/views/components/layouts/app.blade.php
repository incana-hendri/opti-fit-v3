{{-- Signed-in customer shell: top bar + bottom tab bar on mobile, top nav from 1024px (docs/04). --}}
@props(['title' => null, 'user' => null])

@php($user ??= auth()->user())

<x-layouts.base :title="$title">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-full focus:bg-brand focus:px-4 focus:py-2 focus:text-ink">Skip to content</a>

    <header class="sticky top-0 z-30 border-b border-line bg-surface/90 backdrop-blur supports-[backdrop-filter]:bg-surface/75">
        <div class="mx-auto flex h-14 max-w-[960px] items-center gap-6 px-4 lg:h-16 lg:px-6">
            <a href="{{ route('home') }}" class="shrink-0" aria-label="OptiFit home">
                <x-logo class="h-7 w-auto" />
            </a>

            <nav aria-label="Main" class="hidden lg:block">
                <x-tab-bar variant="top" />
            </nav>

            <div x-data="{ open: false }" class="relative ml-auto" @keydown.escape.window="open = false" @click.outside="open = false">
                <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="menu"
                        class="flex min-h-11 items-center gap-2 rounded-full pl-1 pr-2 text-sm font-medium text-muted hover:text-white">
                    <span class="grid size-9 place-items-center rounded-full bg-raised font-semibold text-brand" aria-hidden="true">
                        {{ mb_substr($user?->first_name ?? '?', 0, 1) }}
                    </span>
                    <span class="sr-only sm:not-sr-only">{{ $user?->first_name }}</span>
                    <x-heroicon-o-chevron-down class="size-4" aria-hidden="true" />
                </button>
                <div x-cloak x-show="open" x-transition.opacity.duration.150ms role="menu"
                     class="absolute right-0 mt-2 w-52 overflow-hidden rounded-2xl border border-line bg-raised py-1 shadow-lg">
                    <a role="menuitem" href="{{ url('/membership') }}" class="block px-4 py-3 text-sm hover:bg-white/5">Membership</a>
                    <a role="menuitem" href="{{ url('/account') }}" class="block px-4 py-3 text-sm hover:bg-white/5">Account</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button role="menuitem" type="submit" class="block w-full px-4 py-3 text-left text-sm hover:bg-white/5">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-[960px] px-4 pt-6 pb-28 lg:px-6 lg:pb-12">
        {{ $slot }}
    </main>

    <nav aria-label="Main" class="fixed inset-x-0 bottom-0 z-30 lg:hidden">
        <x-tab-bar />
    </nav>
</x-layouts.base>
