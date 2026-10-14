{{-- Add/edit family member (BR-2.3). Expects a Livewire component with a MemberForm at $memberForm and a saveMember() action. --}}
<x-sheet name="member" :title="$this->memberForm->memberId ? 'Edit family member' : 'Add family member'">
    <form wire:submit="saveMember" class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input label="First name" name="memberForm.first_name" wire:model="memberForm.first_name" autocomplete="off" />
            <x-input label="Last name" name="memberForm.last_name" wire:model="memberForm.last_name" autocomplete="off" />
        </div>
        <x-input label="Date of birth" name="memberForm.date_of_birth" type="date" wire:model="memberForm.date_of_birth" max="{{ today()->toDateString() }}" />
        <x-select label="Gender" name="memberForm.gender" wire:model="memberForm.gender" placeholder="Choose…" :options="$genders" />

        <div class="space-y-1.5">
            <label for="memberForm.health_notes" class="block text-sm font-medium text-muted">Health notes <span class="text-subtle">(optional)</span></label>
            <textarea id="memberForm.health_notes" wire:model="memberForm.health_notes" rows="3" aria-describedby="memberForm.health_notes-hint"
                      class="block w-full rounded-xl border border-line bg-raised px-4 py-3 text-base text-white placeholder:text-subtle focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40"></textarea>
            <p id="memberForm.health_notes-hint" class="text-sm text-subtle">Injuries or conditions our trainer should know about. Only the studio sees this.</p>
            @error('memberForm.health_notes') <p class="text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <x-button type="submit" block wire:loading.attr="disabled" wire:target="saveMember">
            {{ $this->memberForm->memberId ? 'Save changes' : 'Add to family' }}
        </x-button>
    </form>
</x-sheet>
