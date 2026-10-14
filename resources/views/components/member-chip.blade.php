@props(['name', 'booked' => false])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium',
    'bg-success/15 text-success' => $booked,
    'bg-white/10 text-muted' => ! $booked,
]) }}>
    {{ $name }}
    @if ($booked)
        <x-heroicon-o-check class="size-4" aria-hidden="true" />
        <span class="sr-only">(booked)</span>
    @endif
</span>
