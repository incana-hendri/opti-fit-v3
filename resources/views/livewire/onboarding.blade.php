<div>
    {{-- Progress dots --}}
    <ol class="mb-6 flex items-center justify-center gap-2" aria-label="Step {{ $step }} of 3">
        @foreach ([1 => 'About you', 2 => 'Your family', 3 => 'Emergency contact'] as $number => $label)
            <li @class([
                'h-2 rounded-full transition-all duration-200',
                'w-8 bg-brand' => $number === $step,
                'w-2 bg-brand/60' => $number < $step,
                'w-2 bg-white/20' => $number > $step,
            ])><span class="sr-only">{{ $label }}{{ $number === $step ? ' (current)' : '' }}</span></li>
        @endforeach
    </ol>

    @if ($step === 1)
        <form wire:submit="saveAboutYou" class="space-y-6">
            <div>
                <h1 class="text-2xl">About you</h1>
                <p class="mt-1 text-muted">Let's set up your family. It takes about two minutes.</p>
            </div>

            <fieldset class="space-y-3">
                <legend class="mb-2 text-sm font-medium text-muted">Will you be training too?</legend>
                <x-radio-card name="trains" value="yes" title="Yes, I'll train too" wire:model.live="trains" />
                <x-radio-card name="trains" value="no" title="No, just my family" wire:model.live="trains" />
                @error('trains') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            </fieldset>

            @if ($trains === 'yes')
                <x-select label="Your gender" name="holderGender" wire:model="holderGender" placeholder="Choose…" :options="$genders" />
            @endif

            <x-input label="Household name" name="householdName" wire:model="householdName" hint="Shown to the studio, e.g. on class lists." />

            <x-button type="submit" block>Continue</x-button>
        </form>
    @elseif ($step === 2)
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl">Your family</h1>
                <p class="mt-1 text-muted">Add everyone who will come to classes.</p>
            </div>

            @if ($members->isEmpty())
                <x-empty-state title="No family members yet" icon="heroicon-o-users">
                    Add at least one person to continue.
                </x-empty-state>
            @else
                <ul class="space-y-2">
                    @foreach ($members as $member)
                        <li wire:key="member-{{ $member->id }}" class="flex items-center gap-3 rounded-2xl border border-line bg-raised px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ $member->fullName() }}</p>
                                <p class="text-sm text-muted">{{ $member->age() }} years · {{ $member->gender->label() }}{{ $member->is_account_holder ? ' · You' : '' }}</p>
                            </div>
                            @unless ($member->is_account_holder)
                                <x-button variant="ghost" size="sm" wire:click="editMember({{ $member->id }})">Edit</x-button>
                                <x-button variant="ghost" size="sm" class="text-muted" wire:click="removeMember({{ $member->id }})" aria-label="Remove {{ $member->first_name }}">Remove</x-button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif

            @error('members') <p class="text-sm text-danger">{{ $message }}</p> @enderror

            <x-button variant="secondary" block wire:click="addMember">Add family member</x-button>

            <div class="flex gap-3">
                <x-button variant="ghost" wire:click="back">Back</x-button>
                <x-button class="flex-1" wire:click="toEmergencyContact">Continue</x-button>
            </div>
        </div>

        @include('livewire.partials.member-sheet')
    @else
        <form wire:submit="finish" class="space-y-6">
            <div>
                <h1 class="text-2xl">Emergency contact</h1>
                <p class="mt-1 text-muted">Who should we call if something happens during a class?</p>
            </div>

            <x-input label="Name" name="emergencyName" wire:model="emergencyName" autocomplete="off" />
            <x-input label="Phone" name="emergencyPhone" type="tel" wire:model="emergencyPhone" autocomplete="off" />

            <div class="flex gap-3">
                <x-button variant="ghost" wire:click="back">Back</x-button>
                <x-button type="submit" class="flex-1">Finish</x-button>
            </div>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-8 text-center">
        @csrf
        <button type="submit" class="min-h-11 text-sm text-subtle hover:text-white">Log out</button>
    </form>
</div>
