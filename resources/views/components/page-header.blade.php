@props(['title', 'subtitle' => null])

<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4') }}>
    <div>
        <h1 class="text-2xl lg:text-3xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
