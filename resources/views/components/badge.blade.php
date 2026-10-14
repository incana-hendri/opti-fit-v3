{{-- Status is always text plus colour, never colour alone (docs/08). --}}
@props(['color' => 'neutral'])

@php
    $colors = [
        'neutral' => 'bg-white/10 text-muted',
        'orange' => 'bg-brand/15 text-brand',
        'success' => 'bg-success/15 text-success',
        'danger' => 'bg-danger/15 text-danger',
        'info' => 'bg-info/15 text-info',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-sm font-medium '.$colors[$color]) }}>
    {{ $slot }}
</span>
