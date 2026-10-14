@props(['as' => 'div'])

<{{ $as }} {{ $attributes->class('rounded-card border border-line bg-surface p-4 sm:p-5') }}>
    {{ $slot }}
</{{ $as }}>
