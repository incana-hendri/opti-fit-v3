{{--
    Home (docs/04 § /home). Memberships and credits arrive in Milestone 2, bookings in Milestone 3;
    until then a household always sees the "No package yet" status card.
--}}
@php($household = auth()->user()->household->loadCount('activeMembers'))

<x-layouts.app title="Home">
    <div class="space-y-6">
        <x-page-header :title="'Hi '.auth()->user()->first_name" subtitle="Welcome to OptiFit." />

        <x-card class="relative overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-brand/10 blur-2xl"></div>
            <p class="relative text-lg font-semibold">No package yet</p>
            <p class="relative mt-1 text-muted">Choose a monthly package and we'll get your family booked in.</p>
            <x-button href="{{ url('/membership/request') }}" class="relative mt-5">Choose a package</x-button>
        </x-card>

        <x-card class="flex items-center gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-brand/15 text-brand">
                <x-heroicon-o-users class="size-6" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $household->name }}</p>
                <p class="text-sm text-muted">{{ $household->active_members_count }} {{ \Illuminate\Support\Str::plural('member', $household->active_members_count) }}</p>
            </div>
            <x-button href="{{ route('family') }}" variant="ghost" size="sm">Manage</x-button>
        </x-card>
    </div>
</x-layouts.app>
