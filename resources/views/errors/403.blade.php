{{-- A signed-in customer goes back to /home, an admin to the panel, everyone else to the landing page. --}}
@php
    $user = auth()->user();
    $home = $user === null ? '/' : ($user->isAdmin() ? url('/admin') : route('home'));
@endphp
<x-error-page code="403" title="You can't open this page" :home="$home" home-label="Back to the start">
    Your account doesn't have access to this page. If you think that's a mistake, please contact the studio.
</x-error-page>
