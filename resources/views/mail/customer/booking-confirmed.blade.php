@php
    use App\Support\Cutoff;
    use Illuminate\Support\Str;

    $names = $bookings->pluck('member.first_name')->join(', ', ' and ');
    $cutoff = Cutoff::at($session);
@endphp
<x-mail::message>
# You're booked

Hi {{ $bookings->first()->household->user->first_name }}, **{{ $names }}** {{ $bookings->count() === 1 ? 'is' : 'are' }} booked into **{{ $session->title }}**.

**{{ $session->starts_at->format('D j M, H:i') }}** · {{ $session->duration_minutes }} minutes<br>
{{ $session->location->name }}, {{ $session->location->address }}

@if ($cutoff->isFuture())
Free cancellation until **{{ $cutoff->format('D j M, H:i') }}**. After that the credit isn't returned.
@else
The free-cancellation time for this class has already passed.
@endif

You have **{{ $creditsLeft }} {{ Str::plural('credit', $creditsLeft) }}** left this month.

<x-mail::button :url="route('bookings')">
View my bookings
</x-mail::button>
</x-mail::message>
