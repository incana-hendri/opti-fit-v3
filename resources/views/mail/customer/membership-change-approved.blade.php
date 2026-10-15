<x-mail::message>
# Your package is changing

Hi {{ $membership->household->user->first_name }}, your change is approved. It starts on **1 {{ \App\Support\Period::label($membership->starts_period) }}**.

Until then your current package carries on, and the credits you already have stay usable until the month ends.

**Now:** {{ $previous->package->name }}, {{ $previous->monthly_credits }} credits per month

**From 1 {{ \App\Support\Period::label($membership->starts_period) }}:**

@include('mail.partials.membership-details')

<x-mail::button :url="route('membership')">
View my membership
</x-mail::button>
</x-mail::message>
