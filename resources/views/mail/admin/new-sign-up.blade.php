<x-mail::message>
# New sign-up

**{{ $user->fullName() }}** has verified their email and can now set up their family.

<x-mail::table>
| | |
|:--|:--|
| Email | {{ $user->email }} |
| Phone | {{ $user->phone }} |
| Signed up | {{ $user->created_at->format('D j M, H:i') }} |
</x-mail::table>

They'll send a package request once their family is set up.

<x-mail::button :url="url('/admin')">
Open the admin panel
</x-mail::button>
</x-mail::message>
