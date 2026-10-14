{{-- The brand logo is always the image file, never live text (docs/08). --}}
@props(['tagline' => false])

@if ($tagline)
    <img src="{{ asset('images/brand/logo-on-dark-tagline@1x.png') }}"
         srcset="{{ asset('images/brand/logo-on-dark-tagline@1x.png') }} 1x, {{ asset('images/brand/logo-on-dark-tagline@2x.png') }} 2x"
         width="480" height="159" alt="OptiFit Functional Fitness Training"
         {{ $attributes->class('h-auto') }}>
@else
    <img src="{{ asset('images/brand/logo-on-dark@1x.png') }}"
         srcset="{{ asset('images/brand/logo-on-dark@1x.png') }} 1x, {{ asset('images/brand/logo-on-dark@2x.png') }} 2x"
         width="320" height="99" alt="OptiFit"
         {{ $attributes->class('h-8 w-auto') }}>
@endif
