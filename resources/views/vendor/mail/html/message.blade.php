<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer (docs/06) --}}
<x-slot:footer>
<x-mail::footer>
OptiFit · Gene Louw Activity Centre, Durbanville · info@opti-fit.co.za
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
