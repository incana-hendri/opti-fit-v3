@props(['label' => null, 'name' => null, 'id' => null, 'description' => null, 'bag' => 'default'])

@php
    $id ??= $name ?? 'cb-'.\Illuminate\Support\Str::random(6);
    $error = $name ? $errors->getBag($bag)->first($name) : null;
@endphp

<div>
    <label for="{{ $id }}" class="flex min-h-11 cursor-pointer items-start gap-3 py-2 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
        <input id="{{ $id }}" type="checkbox" @if ($name) name="{{ $name }}" @endif
               @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
               {{ $attributes->class('mt-0.5 size-5 shrink-0 rounded border-line bg-raised accent-brand') }}>
        <span class="text-base">
            {{ $label ?? $slot }}
            @if ($description)
                <span class="block text-sm text-subtle">{{ $description }}</span>
            @endif
        </span>
    </label>
    @if ($error)
        <p id="{{ $id }}-error" class="text-sm text-danger">{{ $error }}</p>
    @endif
</div>
