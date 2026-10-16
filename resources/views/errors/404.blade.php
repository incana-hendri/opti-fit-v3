@php
    $user = auth()->user();
    $home = $user === null ? '/' : ($user->isAdmin() ? url('/admin') : route('home'));
@endphp
<x-error-page code="404" title="We couldn't find that page" :home="$home" home-label="Back to the start">
    The link may be old or mistyped. Let's get you back on track.
</x-error-page>
