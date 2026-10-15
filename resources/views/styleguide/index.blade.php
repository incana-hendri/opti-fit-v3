{{-- Local-only visual QA for every component in docs/08. Not routed in production. --}}
<x-layouts.public title="Styleguide">
    <div class="mx-auto max-w-3xl space-y-12 px-4 py-10 lg:px-6">
        <x-page-header title="Styleguide" subtitle="Every customer-side component, for visual QA at 390px and 1280px.">
            <x-slot:actions>
                <x-button href="{{ url('/_styleguide/app') }}" variant="secondary" size="sm">App shell</x-button>
                <x-button href="{{ url('/_styleguide/auth') }}" variant="secondary" size="sm">Auth shell</x-button>
            </x-slot:actions>
        </x-page-header>

        <section class="space-y-4">
            <h2 class="text-xl">Colours</h2>
            <div class="grid grid-cols-3 gap-3 text-xs sm:grid-cols-5">
                @foreach (['night', 'surface', 'raised', 'brand', 'brand-light', 'brand-dark', 'success', 'danger', 'info', 'subtle'] as $c)
                    <div>
                        <div class="h-14 rounded-xl border border-line bg-{{ $c }}"></div>
                        <p class="mt-1 text-muted">{{ $c }}</p>
                    </div>
                @endforeach
            </div>
            {{-- Full class names so Tailwind generates them: bg-night bg-surface bg-raised bg-brand bg-brand-light bg-brand-dark bg-success bg-danger bg-info bg-subtle --}}
        </section>

        <section class="space-y-3">
            <h2 class="text-xl">Typography</h2>
            <p class="text-4xl font-semibold">48 Display</p>
            <p class="text-3xl font-semibold">36 Heading</p>
            <p class="text-2xl font-semibold">28 Page title</p>
            <p class="text-xl font-semibold">22 Section</p>
            <p class="text-lg">18 Lead text</p>
            <p>16 Body text. Warm, short and direct: “Booked! See you Monday at 05:00.”</p>
            <p class="text-sm text-muted">14 Secondary text</p>
            <p class="text-xs text-subtle">12 Caption</p>
            <p class="text-2xl font-semibold tabular-nums text-brand">05:00 · 16:00 · 12 credits</p>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-button</h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-button>Primary</x-button>
                <x-button variant="secondary">Secondary</x-button>
                <x-button variant="ghost">Ghost</x-button>
                <x-button variant="danger">Danger</x-button>
                <x-button disabled>Disabled</x-button>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <x-button size="sm">Small</x-button>
                <x-button size="md">Medium</x-button>
                <x-button size="lg">Large</x-button>
            </div>
            <x-button block>Book 2 people</x-button>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-badge</h2>
            <div class="flex flex-wrap gap-2">
                <x-badge color="success">8 places left</x-badge>
                <x-badge color="orange">2 places left</x-badge>
                <x-badge>Full</x-badge>
                <x-badge color="danger">Cancelled by studio</x-badge>
                <x-badge color="info">Pending</x-badge>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-member-chip</h2>
            <div class="flex flex-wrap gap-2">
                <x-member-chip name="Mia" booked />
                <x-member-chip name="Anna" booked />
                <x-member-chip name="Johan" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-card</h2>
            <x-card>
                <p class="font-semibold">A card on night</p>
                <p class="text-sm text-muted">Surface background, 16px radius, hairline border. Depth comes from surface steps, not shadows.</p>
            </x-card>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-credit-meter</h2>
            <x-credit-meter :balance="12" :granted="16" :used="4" month="October" expires="31 Oct" />
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-status-card</h2>
            <x-status-card title="No package yet">
                Choose a monthly package and we'll get your family booked in.
                <x-slot:action>
                    <x-button>Choose a package</x-button>
                </x-slot:action>
            </x-status-card>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-day-strip</h2>
            <x-day-strip :selected="$days[1]['date']" :days="$days" />
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-session-card</h2>
            <x-session-card time="05:00" title="Morning Functional Fitness" location="Gene Louw Activity Centre" :places-left="8" :booked-members="['Mia']" />
            <x-session-card time="16:00" title="Afternoon Functional Fitness" location="Gene Louw Activity Centre" :places-left="2" />
            <x-session-card time="15:30" title="Afternoon Functional Fitness" location="Gene Louw Activity Centre" :places-left="0" state="full" />
            <x-session-card time="05:00" title="Morning Functional Fitness" location="Gene Louw Activity Centre" :places-left="5" state="closed" closed-note="It closed at Sun 2 Nov 21:00." />
            <x-session-card time="16:00" title="Afternoon Functional Fitness" location="Gene Louw Activity Centre" :places-left="6" state="all-booked" :booked-members="['Anna', 'Mia']" />
            <x-session-card time="05:00" title="Morning Functional Fitness" location="Gene Louw Activity Centre" :places-left="9">
                <x-slot:action><x-button href="#" variant="secondary" size="sm">Get a package</x-button></x-slot:action>
            </x-session-card>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-places-badge</h2>
            <div class="flex flex-wrap gap-2">
                <x-places-badge :places-left="12" />
                <x-places-badge :places-left="3" />
                <x-places-badge :places-left="1" />
                <x-places-badge :places-left="0" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-timetable-list</h2>
            <x-timetable-list :sessions="$sessions" />
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">Form controls</h2>
            <x-card class="space-y-5">
                <x-input label="First name" name="sg_first_name" placeholder="Anna" autocomplete="given-name" />
                <x-input label="Email" name="sg_email" type="email" hint="We'll send a verification link." />
                <x-input label="Password" name="sg_password" revealable />
                <x-input label="Phone (with error)" name="sg_phone" />
                <x-select label="Gender" name="sg_gender" placeholder="Choose…" :options="['female' => 'Female', 'male' => 'Male', 'other' => 'Other']" />
                <x-checkbox name="sg_terms" label="I accept the Terms and Privacy Policy" />
                <x-checkbox name="sg_mia" label="Mia" description="Already booked" checked disabled />
            </x-card>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-radio-card</h2>
            <div class="space-y-3">
                <x-radio-card name="sg_package" value="1" title="1× per week" aside="R280" checked>4 credits / month · ≈ R70 per class</x-radio-card>
                <x-radio-card name="sg_package" value="2" title="2× per week" aside="R520">8 credits / month · ≈ R65 per class</x-radio-card>
                <x-radio-card name="sg_package" value="3" title="3× per week" aside="R720">12 credits / month · ≈ R60 per class</x-radio-card>
            </div>
        </section>

        <section class="space-y-4" x-data>
            <h2 class="text-xl">x-sheet and x-toast</h2>
            <div class="flex flex-wrap gap-3">
                <x-button @click="$dispatch('open-sheet', 'sg-book')">Open sheet</x-button>
                <x-button variant="secondary" @click="$dispatch('toast', { message: 'Booked! See you Monday 05:00', type: 'success' })">Success toast</x-button>
                <x-button variant="secondary" @click="$dispatch('toast', { message: 'That class just filled up.', type: 'error' })">Error toast</x-button>
            </div>

            <x-sheet name="sg-book" title="Book Morning Functional Fitness">
                <p class="mb-4 text-sm text-muted">Mon 3 Nov, 05:00 · Gene Louw Activity Centre</p>
                <div class="divide-y divide-line">
                    <x-checkbox name="sg_sheet_anna" label="Anna" checked />
                    <x-checkbox name="sg_sheet_mia" label="Mia" description="Already booked" checked disabled />
                    <x-checkbox name="sg_sheet_johan" label="Johan" checked />
                </div>
                <p class="mt-4 font-semibold tabular-nums">2 credits · you'll have 6 left</p>
                <p class="mt-1 text-sm text-subtle">Free cancellation until Sun 2 Nov 21:00</p>
                <x-button block class="mt-6" @click="$dispatch('close-sheet', 'sg-book'); $dispatch('toast', { message: 'Booked! See you Monday 05:00' })">Book 2 people</x-button>
            </x-sheet>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-empty-state</h2>
            <x-empty-state title="No bookings yet" icon="heroicon-o-calendar-days">
                Pick a class and book your family in.
                <x-slot:action>
                    <x-button href="#">Book a class</x-button>
                </x-slot:action>
            </x-empty-state>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl">x-tab-bar</h2>
            <div class="overflow-hidden rounded-card border border-line">
                <x-tab-bar />
            </div>
            <x-tab-bar variant="top" />
        </section>
    </div>
</x-layouts.public>
