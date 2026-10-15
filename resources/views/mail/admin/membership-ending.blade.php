<x-mail::message>
# Membership ending

**{{ $membership->household->name }}** ({{ $membership->household->user->fullName() }}) cancelled their **{{ $membership->package->name }}** membership.

It ends on {{ \App\Support\Period::lastDay($membership->ends_period)->format('D j M') }}. Credits already granted stay usable until then.

<x-mail::button :url="url('/admin/memberships')">
Open memberships
</x-mail::button>
</x-mail::message>
