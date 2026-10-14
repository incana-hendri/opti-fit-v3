<x-mail::message>
# Your {{ \App\Support\Period::label($period) }} credits are ready

Hi {{ $membership->household->user->first_name }}, **{{ $membership->monthly_credits }} {{ \Illuminate\Support\Str::plural('credit', $membership->monthly_credits) }}** have been added for {{ \App\Support\Period::label($period) }}.

They expire on **{{ \App\Support\Period::lastDay($period)->format('D j M') }}**, so book early.

<x-mail::button :url="route('home')">
Book now
</x-mail::button>
</x-mail::message>
