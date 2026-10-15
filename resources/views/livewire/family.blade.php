<div class="space-y-8">
    <x-page-header title="Family" subtitle="Everyone in your household who comes to classes.">
        <x-slot:actions>
            <x-button size="sm" wire:click="addMember">Add member</x-button>
        </x-slot:actions>
    </x-page-header>

    <section aria-labelledby="members-heading" class="space-y-3">
        <h2 id="members-heading" class="sr-only">Members</h2>

        @forelse ($activeMembers as $member)
            <x-card wire:key="member-{{ $member->id }}" class="flex items-center gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-raised font-semibold text-brand" aria-hidden="true">
                    {{ mb_substr($member->first_name, 0, 1) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">
                        {{ $member->fullName() }}
                        @if ($member->is_account_holder) <span class="text-sm font-normal text-subtle">(you)</span> @endif
                    </p>
                    <p class="text-sm text-muted">{{ $member->age() }} years · {{ $member->gender->label() }}</p>
                    @if (in_array($member->id, $coveredIds, true))
                        <x-badge color="success" class="mt-1">Covered by your package</x-badge>
                    @endif
                </div>
                <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="menu"
                            class="grid size-11 place-items-center rounded-full text-subtle hover:bg-white/5 hover:text-white"
                            aria-label="Options for {{ $member->first_name }}">
                        <x-heroicon-o-ellipsis-vertical class="size-6" aria-hidden="true" />
                    </button>
                    <div x-cloak x-show="open" x-transition.opacity.duration.150ms role="menu"
                         class="absolute right-0 z-10 mt-1 w-40 overflow-hidden rounded-2xl border border-line bg-raised py-1 shadow-lg">
                        <button role="menuitem" type="button" class="block w-full px-4 py-3 text-left text-sm hover:bg-white/5" @click="open = false" wire:click="editMember({{ $member->id }})">Edit</button>
                        <button role="menuitem" type="button" class="block w-full px-4 py-3 text-left text-sm hover:bg-white/5" @click="open = false" wire:click="confirmArchive({{ $member->id }})">Archive</button>
                        @if ($member->canBeDeleted())
                            <button role="menuitem" type="button" class="block w-full px-4 py-3 text-left text-sm text-danger hover:bg-white/5" @click="open = false" wire:click="confirmDelete({{ $member->id }})">Delete</button>
                        @endif
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state title="No family members yet" icon="heroicon-o-users">
                Add the people who will come to classes.
                <x-slot:action>
                    <x-button wire:click="addMember">Add member</x-button>
                </x-slot:action>
            </x-empty-state>
        @endforelse

        @if ($archivedMembers->isNotEmpty())
            <details class="group rounded-card border border-line">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between px-4 text-sm font-medium text-muted">
                    Archived ({{ $archivedMembers->count() }})
                    <x-heroicon-o-chevron-down class="size-5 transition-transform group-open:rotate-180" aria-hidden="true" />
                </summary>
                <ul class="divide-y divide-line border-t border-line">
                    @foreach ($archivedMembers as $member)
                        <li wire:key="archived-{{ $member->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                            <span class="text-muted">{{ $member->fullName() }}</span>
                            <x-badge>Archived</x-badge>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    <section aria-labelledby="emergency-heading">
        <x-card>
            <h2 id="emergency-heading" class="text-lg">Emergency contact</h2>
            <p class="mt-1 text-sm text-muted">Who we call if something happens during a class.</p>
            <form wire:submit="saveEmergencyContact" class="mt-5 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input label="Name" name="emergencyName" wire:model="emergencyName" autocomplete="off" />
                    <x-input label="Phone" name="emergencyPhone" type="tel" wire:model="emergencyPhone" autocomplete="off" />
                </div>
                <x-button type="submit" variant="secondary">Save emergency contact</x-button>
            </form>
        </x-card>
    </section>

    <section aria-labelledby="household-heading">
        <x-card>
            <h2 id="household-heading" class="text-lg">Household name</h2>
            <form wire:submit="saveHouseholdName" class="mt-5 space-y-5">
                <x-input label="Name" name="householdName" wire:model="householdName" hint="Shown to the studio, e.g. on class lists." />
                <x-button type="submit" variant="secondary">Save name</x-button>
            </form>
        </x-card>
    </section>

    @include('livewire.partials.member-sheet')

    <x-sheet name="confirm" :title="$pending && $pending['action'] === 'delete' ? 'Delete family member?' : 'Archive family member?'">
        @if ($pendingMember)
            <p class="text-muted">
                @if ($pending['action'] === 'delete')
                    {{ $pendingMember->first_name }} will be removed from your family. This can't be undone.
                @else
                    {{ $pendingMember->first_name }} won't be bookable any more. Their history is kept.
                @endif
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <x-button :variant="$pending['action'] === 'delete' ? 'danger' : 'primary'" class="sm:flex-1" wire:click="runPending">
                    {{ $pending['action'] === 'delete' ? 'Delete' : 'Archive' }} {{ $pendingMember->first_name }}
                </x-button>
                <x-button variant="secondary" class="sm:flex-1" @click="$dispatch('close-sheet', 'confirm')">Keep {{ $pendingMember->first_name }}</x-button>
            </div>
        @endif
    </x-sheet>
</div>
