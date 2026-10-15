{{-- Places left in a session: green above 3, orange 1-3, grey "Full" (docs/04 § /book). Always text as well as colour. --}}
@props(['placesLeft'])

@php
    [$color, $text] = match (true) {
        $placesLeft <= 0 => ['neutral', 'Full'],
        $placesLeft <= 3 => ['orange', $placesLeft.' '.\Illuminate\Support\Str::plural('place', $placesLeft).' left'],
        default => ['success', $placesLeft.' places left'],
    };
@endphp

<x-badge :color="$color" {{ $attributes }}>{{ $text }}</x-badge>
