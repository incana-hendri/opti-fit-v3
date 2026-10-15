<?php

namespace App\Livewire;

use App\Enums\Gender;
use App\Enums\MembershipStatus;
use App\Livewire\Forms\MemberForm;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * /family: members, emergency contact and household name (docs/04 § Family; BR-2.3 to 2.7).
 */
class Family extends Component
{
    public MemberForm $memberForm;

    public string $householdName = '';

    public string $emergencyName = '';

    public string $emergencyPhone = '';

    /** @var array{action: 'archive'|'delete', memberId: int}|null */
    public ?array $pending = null;

    public function mount(): void
    {
        $household = $this->household();
        $this->householdName = $household->name;
        $this->emergencyName = (string) $household->emergency_contact_name;
        $this->emergencyPhone = (string) $household->emergency_contact_phone;
    }

    public function addMember(): void
    {
        $this->memberForm->startNew($this->user()->last_name);
        $this->dispatch('open-sheet', name: 'member');
    }

    public function editMember(int $memberId): void
    {
        $this->memberForm->edit($this->member($memberId));
        $this->dispatch('open-sheet', name: 'member');
    }

    public function saveMember(): void
    {
        $member = $this->memberForm->save($this->household());
        $this->dispatch('close-sheet', name: 'member');
        $this->dispatch('toast', message: "Saved {$member->first_name}.");
    }

    public function confirmArchive(int $memberId): void
    {
        $this->member($memberId);
        $this->pending = ['action' => 'archive', 'memberId' => $memberId];
        $this->dispatch('open-sheet', name: 'confirm');
    }

    public function confirmDelete(int $memberId): void
    {
        if (! $this->member($memberId)->canBeDeleted()) {
            return;
        }

        $this->pending = ['action' => 'delete', 'memberId' => $memberId];
        $this->dispatch('open-sheet', name: 'confirm');
    }

    public function runPending(): void
    {
        if ($this->pending === null) {
            return;
        }

        $member = $this->member($this->pending['memberId']);

        // BR-2.5: archive keeps the member's history; delete only when nothing refers to them.
        if ($this->pending['action'] === 'archive') {
            $member->forceFill(['archived_at' => now()])->save();
            $message = "{$member->first_name} has been archived.";
        } elseif ($member->canBeDeleted()) {
            $member->delete();
            $message = "{$member->first_name} has been removed.";
        }

        $this->pending = null;
        $this->dispatch('close-sheet', name: 'confirm');

        if (isset($message)) {
            $this->dispatch('toast', message: $message);
        }
    }

    public function saveHouseholdName(): void
    {
        $this->validate(['householdName' => ['required', 'string', 'max:255']]);
        $this->household()->update(['name' => $this->householdName]);
        $this->dispatch('toast', message: 'Household name saved.');
    }

    // BR-2.6
    public function saveEmergencyContact(): void
    {
        $this->validate([
            'emergencyName' => ['required', 'string', 'max:255'],
            'emergencyPhone' => Phone::rules(),
        ], [
            'emergencyPhone.regex' => 'Please enter a valid phone number.',
        ], [
            'emergencyName' => 'name',
            'emergencyPhone' => 'phone',
        ]);

        $this->household()->update([
            'emergency_contact_name' => $this->emergencyName,
            'emergency_contact_phone' => $this->emergencyPhone,
        ]);
        $this->dispatch('toast', message: 'Emergency contact saved.');
    }

    public function render(): View
    {
        $members = $this->household()->members()->withCount(['memberships', 'bookings'])->orderByDesc('is_account_holder')->oldest()->get();

        return view('livewire.family', [
            'activeMembers' => $members->reject->isArchived(),
            'archivedMembers' => $members->filter->isArchived(),
            // BR-4.10: covered means part of the active membership in effect now.
            'coveredIds' => $this->coveredMemberIds(),
            'pendingMember' => $this->pending ? $members->firstWhere('id', $this->pending['memberId']) : null,
            'genders' => Gender::options(),
        ])->title('Family');
    }

    /**
     * @return list<int>
     */
    private function coveredMemberIds(): array
    {
        $membership = $this->household()->membershipInEffect();

        return $membership?->status === MembershipStatus::Active ? $membership->members()->pluck('members.id')->all() : [];
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    private function household(): Household
    {
        return $this->user()->household()->firstOrFail();
    }

    // BR-2.7: members are only ever looked up through the customer's own household.
    private function member(int $memberId): Member
    {
        return $this->household()->members()->findOrFail($memberId);
    }
}
