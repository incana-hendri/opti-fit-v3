<x-mail::message>
# You're in!

Hi {{ $membership->household->user->first_name }}, your package is approved.

@include('mail.partials.membership-details')

It starts in **{{ \App\Support\Period::label($membership->starts_period) }}**.
@if ($firstMonthCredits)
Your first {{ $firstMonthCredits }} {{ \Illuminate\Support\Str::plural('credit', $firstMonthCredits) }} are ready to use now.
@else
Your monthly credits start on 1 {{ \App\Support\Period::label($membership->grants_from_period) }}.
@endif

@if (filled($paymentInstructions))
## How to pay

{{ $paymentInstructions }}
@endif

<x-mail::button :url="route('book')">
Book your first class
</x-mail::button>
</x-mail::message>
