{{-- Every list gets a friendly empty state with one next action (docs/04). --}}
@props(['title', 'icon' => 'heroicon-o-sparkles'])

<div {{ $attributes->class('flex flex-col items-center rounded-card border border-dashed border-line px-6 py-10 text-center') }}>
    <span class="mb-3 grid size-12 place-items-center rounded-full bg-brand/15 text-brand">
        <x-dynamic-component :component="$icon" class="size-6" aria-hidden="true" />
    </span>
    <p class="text-lg font-semibold">{{ $title }}</p>
    @if (trim($slot))
        <p class="mt-1 max-w-xs text-sm text-muted">{{ $slot }}</p>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
