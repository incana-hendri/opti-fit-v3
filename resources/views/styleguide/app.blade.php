{{-- Local-only preview of the signed-in shell with sample content. --}}
<x-layouts.app title="App shell" :user="$user">
    <div class="space-y-6">
        <x-page-header title="Hi Anna" subtitle="Here's your week." />

        <x-credit-meter :balance="12" :granted="16" :used="4" month="October" expires="31 Oct" />

        <section class="space-y-3">
            <h2 class="text-lg">Next up</h2>
            <x-session-card time="05:00" title="Morning Functional Fitness" location="Gene Louw Activity Centre" :places-left="8" state="all-booked" :booked-members="['Anna', 'Mia']" />
        </section>

        <x-button block href="{{ url('/_styleguide') }}">Book a class</x-button>

        <p class="text-sm text-muted">Johan has no class booked this week.</p>
    </div>
</x-layouts.app>
