<x-mail::message>
# We've received your request

Hi {{ $membership->household->user->first_name }}, thanks for choosing OptiFit. Here is what you asked for:

@include('mail.partials.membership-details')

We'll confirm and send payment details by email.
</x-mail::message>
