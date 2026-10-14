@props(['label', 'name', 'type' => 'text', 'id' => null, 'hint' => null, 'revealable' => false])

@php
    $id ??= $name;
    $error = $errors->first($name);
    $describedBy = collect([$hint ? $id.'-hint' : null, $error ? $id.'-error' : null])->filter()->implode(' ');
    $inputClasses = 'block min-h-12 w-full rounded-xl border bg-raised px-4 text-base text-white placeholder:text-subtle '
        .'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40 '
        .($error ? 'border-danger' : 'border-line')
        .($revealable ? ' pr-14' : '');
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-sm font-medium text-muted">{{ $label }}</label>

    <div class="relative" @if ($revealable) x-data="{ show: false }" @endif>
        <input id="{{ $id }}" name="{{ $name }}"
               @if ($revealable) :type="show ? 'text' : 'password'" type="password" @else type="{{ $type }}" @endif
               @if ($type !== 'password' && ! $revealable) value="{{ old($name, $attributes->get('value')) }}" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if ($error) aria-invalid="true" @endif
               {{ $attributes->except('value')->class($inputClasses) }}>

        @if ($revealable)
            <button type="button" @click="show = ! show"
                    class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-xl text-subtle hover:text-white"
                    :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                <x-heroicon-o-eye x-show="! show" class="size-5" aria-hidden="true" />
                <x-heroicon-o-eye-slash x-cloak x-show="show" class="size-5" aria-hidden="true" />
            </button>
        @endif
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-subtle">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-sm text-danger">{{ $error }}</p>
    @endif
</div>
