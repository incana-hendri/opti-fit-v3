<x-mail::message>
# Classes below minimum today

These classes have fewer people booked than their minimum:

@foreach ($sessions as $session)
- **{{ $session->starts_at->format('H:i') }} {{ $session->title }}**: {{ $session->occupied() }} of {{ $session->min_participants }} minimum ({{ $session->capacity }} places)
@endforeach

<x-mail::button :url="url('/admin/class-sessions')">
Open sessions
</x-mail::button>
</x-mail::message>
