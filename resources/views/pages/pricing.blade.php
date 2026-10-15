{{-- Public pricing (docs/04 § /pricing). Prices come ready-made per family size; Alpine only picks one. --}}
<x-layouts.public title="Pricing">
    <div class="mx-auto max-w-5xl px-4 pt-10 lg:px-6"
         x-data="{ size: 1, packages: @js($packages->mapWithKeys(fn (array $package) => [$package['id'] => $package['sizes']])) }">
        <x-page-header title="Simple monthly pricing" subtitle="One monthly package for your whole family. Pay by EFT once we approve your request." />

        <fieldset class="mb-8">
            <legend class="mb-3 text-sm font-medium text-muted">How many family members will train?</legend>
            <div class="inline-flex rounded-full border border-line bg-surface p-1">
                @foreach ($sizes as $size)
                    <label class="relative cursor-pointer">
                        <input type="radio" name="family-size" value="{{ $size }}" class="peer sr-only" x-model.number="size" @checked($size === 1)>
                        <span class="grid min-h-11 min-w-12 place-items-center rounded-full px-4 text-base font-semibold text-muted transition-colors duration-150 peer-checked:bg-brand peer-checked:text-ink peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand">
                            {{ $size === $largestSize ? $size.'+' : $size }}
                            <span class="sr-only">{{ $size === 1 ? 'member' : ($size === $largestSize ? 'or more members' : 'members') }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        @if ($packages->isEmpty())
            <x-empty-state title="Packages are coming soon" icon="heroicon-o-tag">
                We're finalising this month's packages. Check back shortly, or email us.
            </x-empty-state>
        @else
            <div class="grid gap-4 md:grid-cols-3" aria-live="polite">
                @foreach ($packages as $package)
                <x-card as="article" class="flex flex-col p-5 sm:p-6">
                    <h2 class="text-xl">{{ $package['name'] }}</h2>
                    @if ($package['description'])
                        <p class="mt-1 text-sm text-muted">{{ $package['description'] }}</p>
                    @endif

                    <p class="mt-5">
                        <span class="text-3xl font-semibold text-brand tabular-nums whitespace-nowrap" x-text="packages[{{ $package['id'] }}][size].price">{{ $package['sizes'][1]['price'] }}</span>
                        <span class="text-muted"> / month</span>
                    </p>
                    <p class="mt-1 font-medium tabular-nums"><span x-text="packages[{{ $package['id'] }}][size].credits">{{ $package['sizes'][1]['credits'] }}</span> credits / month</p>
                    <p class="mt-1 text-sm text-muted tabular-nums" x-text="packages[{{ $package['id'] }}][size].perClass">{{ $package['sizes'][1]['perClass'] }}</p>
                    <p x-cloak x-show="size === {{ $largestSize }}" class="mt-1 text-sm text-subtle">Price for {{ $largestSize }} or more members.</p>

                    @auth
                        <x-button href="{{ route('membership.request', ['package' => $package['id']]) }}" class="mt-6" block>Choose</x-button>
                    @else
                        <x-button href="{{ route('register') }}" class="mt-6" block>Choose</x-button>
                    @endauth
                </x-card>
                @endforeach
            </div>
        @endif

        <section aria-labelledby="faq-heading" class="mt-14">
            <h2 id="faq-heading" class="mb-4 text-xl">Good to know</h2>
            <div class="divide-y divide-line rounded-card border border-line bg-surface">
                @foreach ([
                    'Do credits roll over?' => 'No. Credits expire at the end of the month, and you can only book classes in the current month.',
                    'How far ahead can I book?' => "You can book up to {$cutoffHours} hours before a class starts.",
                    'What if I can\'t make it?' => "Cancel at least {$cutoffHours} hours before the class and your credit comes straight back.",
                    'How do I pay?' => 'By EFT. After you request a package we approve it and email you the payment details.',
                    'Who can use our credits?' => 'Any family member covered by your package can use the family\'s credits. One credit books one person into one class.',
                ] as $question => $answer)
                    <details class="group">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 font-medium sm:px-5">
                            {{ $question }}
                            <x-heroicon-o-chevron-down class="size-5 shrink-0 text-subtle transition-transform group-open:rotate-180" aria-hidden="true" />
                        </summary>
                        <p class="px-4 pb-4 text-muted sm:px-5">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.public>
