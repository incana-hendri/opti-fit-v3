<x-mail::message>
# Account deletion request

**{{ $household->user->fullName() }}** ({{ $household->name }}) has asked for their account to be deleted.

<x-mail::table>
| | |
|:--|:--|
| Email | {{ $household->user->email }} |
| Phone | {{ $household->user->phone }} |
| Requested | {{ $household->user->deletion_requested_at?->format('D j M, H:i') }} |
</x-mail::table>

Deletion is done in the admin panel with "Anonymise household". Nothing happens automatically.

<x-mail::button :url="url('/admin')">
Open the admin panel
</x-mail::button>
</x-mail::message>
