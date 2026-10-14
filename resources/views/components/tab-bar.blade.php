{{-- The four signed-in sections (docs/04). variant="bottom" is the mobile tab bar, "top" the desktop nav. --}}
@props(['variant' => 'bottom'])

@php
    $tabs = [
        ['label' => 'Home', 'path' => 'home', 'icon' => 'heroicon-o-home'],
        ['label' => 'Book', 'path' => 'book', 'icon' => 'heroicon-o-calendar-days'],
        ['label' => 'Bookings', 'path' => 'bookings', 'icon' => 'heroicon-o-ticket'],
        ['label' => 'Family', 'path' => 'family', 'icon' => 'heroicon-o-users'],
    ];
@endphp

@if ($variant === 'top')
    <ul {{ $attributes->class('flex items-center gap-1') }}>
        @foreach ($tabs as $tab)
            @php($active = request()->is($tab['path'], $tab['path'].'/*'))
            <li>
                <a href="{{ url($tab['path']) }}" @if ($active) aria-current="page" @endif
                   @class([
                       'inline-flex min-h-11 items-center rounded-full px-4 text-sm font-medium transition-colors duration-150',
                       'bg-brand/15 text-brand' => $active,
                       'text-muted hover:text-white' => ! $active,
                   ])>{{ $tab['label'] }}</a>
            </li>
        @endforeach
    </ul>
@else
    <ul {{ $attributes->class('grid grid-cols-4 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur') }}>
        @foreach ($tabs as $tab)
            @php($active = request()->is($tab['path'], $tab['path'].'/*'))
            <li>
                <a href="{{ url($tab['path']) }}" @if ($active) aria-current="page" @endif
                   @class([
                       'flex h-16 flex-col items-center justify-center gap-1 text-xs font-medium transition-colors duration-150',
                       'text-brand' => $active,
                       'text-subtle hover:text-white' => ! $active,
                   ])>
                    <x-dynamic-component :component="$tab['icon']" class="size-6" aria-hidden="true" />
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
@endif
