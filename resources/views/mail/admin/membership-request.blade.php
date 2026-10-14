<x-mail::message>
# Package request

**{{ $membership->household->name }}** ({{ $membership->household->user->fullName() }}) has asked for **{{ $membership->package->name }}**.

@include('mail.partials.membership-details')

@if (filled($membership->customer_note))
Note from the customer: {{ $membership->customer_note }}
@endif

<x-mail::button :url="url('/admin/memberships')">
Review the request
</x-mail::button>
</x-mail::message>
