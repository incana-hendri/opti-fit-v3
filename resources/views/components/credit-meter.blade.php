{{-- The Home credit card: balance, expiry and used vs granted this month (docs/04). --}}
@props(['balance', 'granted', 'used', 'month', 'expires'])

@php($percent = $granted > 0 ? min(100, (int) round($used / $granted * 100)) : 0)

<x-card {{ $attributes->class('relative overflow-hidden') }}>
    <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-brand/10 blur-2xl"></div>

    <p class="relative">
        <span class="text-4xl font-semibold text-brand tabular-nums">{{ $balance }}</span>
        <span class="ml-1 text-lg font-medium">{{ \Illuminate\Support\Str::plural('credit', $balance) }}</span>
    </p>
    <p class="relative mt-1 text-sm text-muted">for {{ $month }} · expire {{ $expires }}</p>

    <div class="relative mt-4">
        <div class="h-1.5 overflow-hidden rounded-full bg-white/10"
             role="progressbar" aria-valuemin="0" aria-valuemax="{{ $granted }}" aria-valuenow="{{ $used }}"
             aria-label="Used {{ $used }} of {{ $granted }} credits this month">
            <div class="h-full rounded-full bg-brand" style="width: {{ $percent }}%"></div>
        </div>
        <p class="mt-2 text-xs text-subtle tabular-nums">{{ $used }} of {{ $granted }} used this month</p>
    </div>
</x-card>
