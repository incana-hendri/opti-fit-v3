{{-- No database access here: the error may be the database. --}}
<x-error-page code="500" title="Something went wrong on our side">
    Please try again in a moment. If it keeps happening, email us at {{ config('mail.from.address') }}.
</x-error-page>
