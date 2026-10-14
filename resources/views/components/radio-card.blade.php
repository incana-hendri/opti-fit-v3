{{-- A selectable card backed by a real radio input (keyboard and screen-reader friendly). --}}
@props(['name', 'value', 'title', 'aside' => null])

<label class="group block cursor-pointer rounded-card border border-line bg-surface p-4 transition-colors duration-150 hover:bg-raised has-[:checked]:border-brand has-[:checked]:bg-brand/5 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand sm:p-5">
    <input type="radio" name="{{ $name }}" value="{{ $value }}" {{ $attributes->class('sr-only') }}>
    <span class="flex items-start gap-3">
        <span aria-hidden="true" class="mt-1 grid size-5 shrink-0 place-items-center rounded-full border-2 border-subtle group-has-[:checked]:border-brand">
            <span class="size-2.5 rounded-full bg-brand opacity-0 group-has-[:checked]:opacity-100"></span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex items-baseline justify-between gap-3">
                <span class="font-semibold">{{ $title }}</span>
                @if ($aside)
                    <span class="font-semibold text-brand tabular-nums">{{ $aside }}</span>
                @endif
            </span>
            @if (trim($slot))
                <span class="mt-1 block text-sm text-muted">{{ $slot }}</span>
            @endif
        </span>
    </span>
</label>
