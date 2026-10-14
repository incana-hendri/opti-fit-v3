<x-mail::message>
# Your membership is ending

Hi {{ $membership->household->user->first_name }}, your **{{ $membership->package->name }}** membership ends on **{{ \App\Support\Period::lastDay($membership->ends_period)->format('D j M') }}**.

Credits you already have stay usable until then. You won't get new credits after that.

Changed your mind? You can undo this until the month ends.

<x-mail::button :url="url('/membership')">
Undo cancellation
</x-mail::button>
</x-mail::message>
