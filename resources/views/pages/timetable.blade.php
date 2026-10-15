{{-- Public timetable (docs/04 § /timetable): the next 14 days, read-only. --}}
<x-layouts.public title="Timetable">
    <div class="mx-auto max-w-3xl px-4 pt-10 lg:px-6">
        <x-page-header title="Timetable" subtitle="The next two weeks of 45-minute classes in Durbanville.">
            <x-slot:actions>
                @auth
                    @unless (auth()->user()->isAdmin())
                        <x-button href="{{ route('book') }}">Book a class</x-button>
                    @endunless
                @else
                    <x-button href="{{ route('login') }}">Log in to book</x-button>
                @endauth
            </x-slot:actions>
        </x-page-header>

        <x-timetable-list :sessions="$sessions" />
    </div>
</x-layouts.public>
