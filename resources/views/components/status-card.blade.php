{{-- The Home status card shown instead of the credit meter: a title, one line of text and an optional action (docs/04 § /home). --}}
@props(['title'])

<x-card {{ $attributes->class('relative overflow-hidden') }}>
    <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-brand/10 blur-2xl"></div>
    <p class="relative text-lg font-semibold">{{ $title }}</p>
    <p class="relative mt-1 text-muted">{{ $slot }}</p>
    @isset($action)
        <div class="relative mt-5">{{ $action }}</div>
    @endisset
</x-card>
