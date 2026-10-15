<x-mail::message>
# Class time changed

**{{ $session->title }}** has moved.

~~{{ $previousStartsAt->format('D j M, H:i') }}~~ → **{{ $session->starts_at->format('D j M, H:i') }}**

Your booking stays as it is. Can't make it? Cancel for free until **{{ $session->cutoffAt()->format('D j M, H:i') }}**.

<x-mail::button :url="route('bookings')">
View my bookings
</x-mail::button>
</x-mail::message>
