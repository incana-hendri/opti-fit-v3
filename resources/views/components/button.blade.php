{{-- Orange buttons always use night text: white on orange fails contrast (docs/08). --}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button', 'block' => false])

@php
    $variants = [
        'primary' => 'bg-brand text-ink hover:bg-brand-light active:bg-brand-dark',
        'secondary' => 'bg-raised text-white border border-line hover:bg-white/10',
        'ghost' => 'text-brand hover:bg-white/5 hover:text-brand-light',
        'danger' => 'bg-danger text-ink hover:brightness-110',
    ];
    $sizes = [
        'sm' => 'min-h-11 px-4 text-sm',
        'md' => 'min-h-12 px-6 text-base',
        'lg' => 'min-h-14 px-8 text-lg',
    ];
    $classes = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-colors duration-150 '
        .'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 '
        .$variants[$variant].' '.$sizes[$size].($block ? ' w-full' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
