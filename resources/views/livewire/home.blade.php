{{-- Home (docs/04 § /home). The next bookings and the Book button arrive in Milestone 3. --}}
@php
    use App\Support\Period;
@endphp

<div class="space-y-6">
    <x-page-header :title="'Hi '.auth()->user()->first_name" subtitle="Welcome to OptiFit." />

    @if ($hasCredits)
        <x-credit-meter :balance="$balance" :granted="$granted" :used="$used" :month="$month" :expires="$expires" />
    @elseif ($scheduled)
        <x-card class="relative overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-brand/10 blur-2xl"></div>
            <p class="relative text-lg font-semibold">Your package starts on 1 {{ Period::label($scheduled->starts_period) }}</p>
            <p class="relative mt-1 text-muted">{{ $scheduled->package->name }} is approved. Your credits arrive on the 1st.</p>
            <x-button href="{{ route('membership') }}" variant="secondary" class="relative mt-5">View my membership</x-button>
        </x-card>
    @elseif ($pending)
        <x-card class="relative overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-brand/10 blur-2xl"></div>
            <p class="relative text-lg font-semibold">Request pending</p>
            <p class="relative mt-1 text-muted">We'll email you once your {{ $pending->package->name }} package is approved.</p>
            <x-button href="{{ route('membership') }}" variant="secondary" class="relative mt-5">View my request</x-button>
        </x-card>
    @else
        <x-card class="relative overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-brand/10 blur-2xl"></div>
            <p class="relative text-lg font-semibold">No package yet</p>
            <p class="relative mt-1 text-muted">Choose a monthly package and we'll get your family booked in.</p>
            <x-button href="{{ route('membership.request') }}" class="relative mt-5">Choose a package</x-button>
        </x-card>
    @endif

    @if ($ending)
        <x-card class="flex flex-wrap items-center justify-between gap-3 border-brand/40">
            <p class="font-medium">Your membership ends on {{ Period::lastDay(Period::current())->format('j M') }}</p>
            <x-button variant="secondary" size="sm" wire:click="undoCancellation">Undo</x-button>
        </x-card>
    @elseif ($scheduled && $hasCredits)
        <x-card class="border-brand/40">
            <p class="font-medium">Your package changes on 1 {{ Period::label($scheduled->starts_period) }}</p>
            <p class="mt-1 text-sm text-muted">{{ $scheduled->package->name }} starts then. Until then your current package carries on.</p>
        </x-card>
    @endif

    @if ($hasCredits && $pending)
        <x-card class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-medium">Request pending: we'll email you once approved.</p>
            <x-button href="{{ route('membership') }}" variant="ghost" size="sm">View</x-button>
        </x-card>
    @endif

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
