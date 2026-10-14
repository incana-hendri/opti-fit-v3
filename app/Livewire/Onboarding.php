<?php

namespace App\Livewire;

use App\Enums\Gender;
use App\Livewire\Forms\MemberForm;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The three-step onboarding wizard (docs/04 § Onboarding; BR-2.1, 2.2, 2.6).
 * Each step saves, so a customer who leaves resumes where they stopped.
 */
class Onboarding extends Component
{
    public int $step = 1;

    public string $trains = '';

    public string $holderGender = '';

    public string $householdName = '';

    public MemberForm $memberForm;

    public string $emergencyName = '';

    public string $emergencyPhone = '';

    public function mount(): mixed
    {
        $household = $this->household();

        if ($household?->isOnboarded() && $household->hasEmergencyContact()) {
            return redirect()->route('home');
        }

        $holder = $household?->members()->where('is_account_holder', true)->first();

        $this->householdName = $household->name ?? $this->user()->last_name.' family';
        $this->trains = $household ? ($holder ? 'yes' : 'no') : '';
        $this->holderGender = $holder->gender->value ?? '';
        $this->emergencyName = (string) $household?->emergency_contact_name;
        $this->emergencyPhone = (string) $household?->emergency_contact_phone;
        $this->step = match (true) {
            $household === null => 1,
            ! $household->isOnboarded() => 2,
            default => 3,
        };

        return null;
    }

    public function saveAboutYou(): void
    {
        $this->validate([
            'trains' => ['required', Rule::in(['yes', 'no'])],
            'holderGender' => ['required_if:trains,yes', 'nullable', Rule::enum(Gender::class)],
            'householdName' => ['required', 'string', 'max:255'],
        ], [
            'trains.required' => 'Please let us know if you will be training.',
            'holderGender.required_if' => 'Please choose your gender.',
        ]);

        DB::transaction(function () {
            $user = $this->user();
            // Query, don't use $user->household: that relation may be cached as null from an earlier step.
            $household = $user->household()->first() ?? $user->household()->make();
            $household->name = $this->householdName;
            $household->save();

            $holder = $household->members()->where('is_account_holder', true)->first();

            // BR-2.2: the account holder trains too, so they get a member record from their own details.
            if ($this->trains === 'yes') {
                $holder ??= $household->members()->make([
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'date_of_birth' => $user->date_of_birth,
                ])->forceFill(['is_account_holder' => true]);
                $holder->gender = Gender::from($this->holderGender);
                $holder->save();
            } elseif ($holder?->canBeDeleted()) {
                $holder->delete();
            }
        });

        $this->step = 2;
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
        $this->memberForm->save($this->household());
        $this->resetErrorBag('members');
        $this->dispatch('close-sheet', name: 'member');
    }

    public function removeMember(int $memberId): void
    {
        $member = $this->member($memberId);

        if ($member->canBeDeleted()) {
            $member->delete();
        }
    }

    public function toEmergencyContact(): void
    {
        if (! $this->household()?->isOnboarded()) {
            $this->addError('members', 'Add at least one family member to continue.');

            return;
        }

        $this->step = 3;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function finish(): mixed
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

        // BR-2.6
        $this->household()->update([
            'emergency_contact_name' => $this->emergencyName,
            'emergency_contact_phone' => $this->emergencyPhone,
        ]);

        return redirect()->route('membership.request')
            ->with('toast', ['message' => 'Last step: choose your package', 'type' => 'success']);
    }

    public function render(): View
    {
        return view('livewire.onboarding', [
            'members' => $this->household()?->activeMembers()->oldest()->get() ?? collect(),
            'genders' => Gender::options(),
        ])->layout('components.layouts.auth')->title('Welcome');
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    private function household(): ?Household
    {
        return $this->user()->household()->first();
    }

    // BR-2.7: members are only ever looked up through the customer's own household.
    private function member(int $memberId): Member
    {
        return $this->user()->household()->firstOrFail()->members()->findOrFail($memberId);
    }
}
