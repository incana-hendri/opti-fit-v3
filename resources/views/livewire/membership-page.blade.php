@php
    use App\Enums\CreditEntryType;
    use App\Support\Money;
    use App\Support\Period;
    use Illuminate\Support\Str;
@endphp

<div class="space-y-8">
    <x-page-header title="Membership" subtitle="Your package and your credits." />

    @if ($current)
        <x-card as="section" aria-labelledby="current-heading" class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">Your package</p>
                    <h2 id="current-heading" class="text-xl">{{ $current->package->name }}</h2>
                </div>
                <x-badge :color="$ending ? 'orange' : $current->status->badgeColor()">{{ $ending ? 'Ending' : $current->status->label() }}</x-badge>
            </div>

            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted">Covers</dt>
                    <dd class="mt-0.5 font-medium">{{ $current->members->pluck('first_name')->join(', ', ' and ') }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Credits</dt>
                    <dd class="mt-0.5 font-medium tabular-nums">{{ $current->monthly_credits }} per month</dd>
                </div>
                <div>
                    <dt class="text-muted">Price</dt>
                    <dd class="mt-0.5 font-medium tabular-nums">{{ Money::format($current->monthly_price_cents) }} per month</dd>
                </div>
                <div>
                    <dt class="text-muted">Since</dt>
                    <dd class="mt-0.5 font-medium">Active since {{ Period::label($current->starts_period) }}</dd>
                </div>
            </dl>

            @if ($ending)
                <div class="rounded-xl border border-brand/40 bg-brand/10 p-4">
                    <p class="font-medium">Your membership ends on {{ $lastDay->format('j M') }}</p>
                    <p class="mt-1 text-sm text-muted">Credits you already have stay usable until then. Changed your mind?</p>
                    <x-button variant="secondary" size="sm" class="mt-3" wire:click="undoCancellation">Undo cancellation</x-button>
                </div>
            @elseif ($scheduled)
                <div class="rounded-xl border border-brand/40 bg-brand/10 p-4">
                    <p class="font-medium">Your package changes on 1 {{ Period::label($scheduled->starts_period) }}</p>
                    <p class="mt-1 text-sm text-muted">Until then your current package carries on. To stop instead, please contact the studio.</p>
                </div>
            @elseif ($current->status === \App\Enums\MembershipStatus::Paused)
                <p class="rounded-xl border border-line bg-raised p-4 text-sm text-muted">Your membership is paused, so no new credits arrive. Please contact the studio to start again.</p>
            @elseif ($canCancel)
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-button href="{{ route('membership.request') }}" variant="secondary" class="sm:flex-1">Change package or members</x-button>
                    <x-button variant="ghost" class="text-muted sm:flex-1" wire:click="confirmCancel">Cancel membership</x-button>
                </div>
            @endif
        </x-card>
    @endif

    @if ($scheduled && ! $current)
        <x-card as="section" aria-labelledby="scheduled-heading" class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">Starting soon</p>
                    <h2 id="scheduled-heading" class="text-xl">{{ $scheduled->package->name }}</h2>
                </div>
                <x-badge color="info">Starts 1 {{ Period::label($scheduled->starts_period) }}</x-badge>
            </div>
            <p class="text-sm text-muted">
                Covers {{ $scheduled->members->pluck('first_name')->join(', ', ' and ') }} ·
                {{ $scheduled->monthly_credits }} credits per month · {{ Money::format($scheduled->monthly_price_cents) }} per month
            </p>
        </x-card>
    @endif

    @if ($pending)
        <x-card as="section" aria-labelledby="pending-heading" class="space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">{{ $current ? 'Requested change' : 'Your request' }}</p>
                    <h2 id="pending-heading" class="text-xl">{{ $pending->package->name }}</h2>
                </div>
                <x-badge :color="$pending->status->badgeColor()">{{ $pending->status->label() }}</x-badge>
            </div>
            <p class="text-sm text-muted">
                Covers {{ $pending->members->pluck('first_name')->join(', ', ' and ') }} ·
                {{ $pending->monthly_credits }} credits per month · {{ Money::format($pending->monthly_price_cents) }} per month
            </p>
            <p class="text-sm text-muted">We'll email you once it's approved.</p>
            <x-button variant="secondary" size="sm" wire:click="confirmWithdraw({{ $pending->id }})">Withdraw request</x-button>
        </x-card>
    @endif

    @if (! $current && ! $scheduled && ! $pending)
        <x-empty-state title="No package yet" icon="heroicon-o-ticket">
            Choose a monthly package and we'll get your family booked in.
            <x-slot:action>
                <x-button href="{{ route('membership.request') }}">Choose a package</x-button>
            </x-slot:action>
        </x-empty-state>
    @endif

    <section aria-labelledby="history-heading" class="space-y-4">
        <h2 id="history-heading" class="text-xl">Credit history</h2>

        @forelse ($history as $period => $entries)
            @php($expired = abs($entries->where('type', CreditEntryType::Expiry)->sum('amount')))
            <x-card as="div" wire:key="history-{{ $period }}">
                <h3 class="text-lg">{{ Period::label($period) }}</h3>
                <ul class="mt-2 divide-y divide-line">
                    @foreach ($entries as $entry)
                        <li wire:key="entry-{{ $entry->id }}" class="flex items-center gap-3 py-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-raised text-muted">
                                <x-dynamic-component :component="$entry->type->icon()" class="size-5" aria-hidden="true" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-base">{{ $entry->description }}</p>
                                <p class="text-xs text-subtle">{{ $entry->created_at->format('D j M') }}</p>
                            </div>
                            <span @class(['font-semibold tabular-nums', 'text-success' => $entry->amount > 0, 'text-muted' => $entry->amount < 0])>
                                {{ $entry->amount > 0 ? '+'.$entry->amount : '−'.abs($entry->amount) }}
                                <span class="sr-only">{{ Str::plural('credit', abs($entry->amount)) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($expired > 0)
                    <p class="mt-1 border-t border-line pt-3 text-sm text-muted">Expired: {{ $expired }}</p>
                @endif
            </x-card>
        @empty
            <x-empty-state title="No credits yet" icon="heroicon-o-clock">
                Your credits and what you used them for will show up here.
            </x-empty-state>
        @endforelse
    </section>

    @if ($canCancel)
        <x-sheet name="cancel" title="Cancel your membership?">
            <p class="text-muted">
                Your membership will end on {{ $lastDay->format('j M') }}. Credits you already have stay usable until then, and you won't get new credits after that.
                You can undo this until the month ends.
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <x-button variant="danger" class="sm:flex-1" wire:click="cancel">Cancel membership</x-button>
                <x-button variant="secondary" class="sm:flex-1" @click="$dispatch('close-sheet', 'cancel')">Keep my membership</x-button>
            </div>
        </x-sheet>
    @endif

    @if ($pending)
        <x-sheet name="withdraw" title="Withdraw your request?">
            <p class="text-muted">We'll forget this request. You can send a new one whenever you like.</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <x-button variant="danger" class="sm:flex-1" wire:click="withdraw">Withdraw request</x-button>
                <x-button variant="secondary" class="sm:flex-1" @click="$dispatch('close-sheet', 'withdraw')">Keep request</x-button>
            </div>
        </x-sheet>
    @endif
</div>
