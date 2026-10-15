<x-mail::message>
# About your package request

Hi {{ $membership->household->user->first_name }}, we couldn't approve your request for **{{ $membership->package->name }}**.

> {{ $membership->decline_reason }}

If you have questions, just reply to this email or write to {{ $contactEmail }}.
</x-mail::message>
