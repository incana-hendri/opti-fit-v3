{{-- The session timed out (CSRF token expired). The back link returns to the page the form was on. --}}
<x-error-page code="419" title="This page expired" home="/" home-label="Back to the home page">
    For your security, pages time out after a while. Go back, reload the page and try again.
    <x-slot:action>
        <x-button href="{{ url()->previous('/') }}">Go back</x-button>
    </x-slot:action>
</x-error-page>
