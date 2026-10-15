<x-mail::message>
# Your booking was cancelled

Hi {{ $booking->household->user->first_name }}, the studio cancelled **{{ $booking->member->first_name }}**'s place in **{{ $booking->classSession->title }}** on {{ $booking->classSession->starts_at->format('D j M, H:i') }}.

@if ($creditReturned)
Your credit has been returned.
@elseif ($booking->credit_charged)
The credit was not returned. If you think that's wrong, just reply to this email.
@else
This was a free booking, so no credit changes.
@endif

<x-mail::button :url="route('book')">
Book another class
</x-mail::button>
</x-mail::message>
