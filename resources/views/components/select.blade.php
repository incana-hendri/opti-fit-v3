{{-- $options: [value => label]. Or pass <option>s in the slot. --}}
@props(['label', 'name', 'id' => null, 'options' => [], 'placeholder' => null, 'hint' => null])

@php
    $id ??= $name;
    $error = $errors->first($name);
    $selected = (string) old($name, $attributes->get('value'));
    $describedBy = collect([$hint ? $id.'-hint' : null, $error ? $id.'-error' : null])->filter()->implode(' ');
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-sm font-medium text-muted">{{ $label }}</label>

    <div class="relative">
        <select id="{{ $id }}" name="{{ $name }}"
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @if ($error) aria-invalid="true" @endif
                {{ $attributes->except('value')->class('block min-h-12 w-full appearance-none rounded-xl border bg-raised pl-4 pr-11 text-base text-white focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40 '.($error ? 'border-danger' : 'border-line')) }}>
            @if ($placeholder)
                <option value="" disabled @selected($selected === '')>{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}" @selected($selected === (string) $value)>{{ $optionLabel }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-heroicon-o-chevron-down class="pointer-events-none absolute top-1/2 right-4 size-5 -translate-y-1/2 text-subtle" aria-hidden="true" />
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-subtle">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-sm text-danger">{{ $error }}</p>
    @endif
</div>
