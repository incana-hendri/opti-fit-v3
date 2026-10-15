<x-mail::message>
# Class cancelled

We're sorry, **{{ $session->title }}** on {{ $session->starts_at->format('D j M, H:i') }} has been cancelled.

@if (filled($session->cancellation_reason))
> {{ $session->cancellation_reason }}
@endif

@if ($creditsReturned > 0)
{{ $creditsReturned }} {{ \Illuminate\Support\Str::plural('credit', $creditsReturned) }} {{ $creditsReturned === 1 ? 'has' : 'have' }} been returned to your account.
@endif

<x-mail::button :url="route('book')">
Book another class
</x-mail::button>
</x-mail::message>
