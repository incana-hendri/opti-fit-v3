<?php

namespace App\Livewire\Forms;

use App\Enums\Gender;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Validation\Rule;
use Livewire\Form;

// BR-2.3: the add/edit family member sheet, shared by onboarding and /family.
class MemberForm extends Form
{
    public ?int $memberId = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $date_of_birth = '';

    public string $gender = '';

    public string $health_notes = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'health_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['date_of_birth' => 'date of birth'];
    }

    public function startNew(string $lastName = ''): void
    {
        $this->reset();
        $this->resetValidation();
        $this->last_name = $lastName;
    }

    public function edit(Member $member): void
    {
        $this->resetValidation();
        $this->memberId = $member->id;
        $this->first_name = $member->first_name;
        $this->last_name = $member->last_name;
        $this->date_of_birth = $member->date_of_birth->toDateString();
        $this->gender = $member->gender->value;
        $this->health_notes = (string) $member->health_notes;
    }

    /**
     * Creates or updates the member, always inside the given household (BR-2.7).
     */
    public function save(Household $household): Member
    {
        $attributes = $this->validate();
        $attributes['health_notes'] = filled($attributes['health_notes']) ? $attributes['health_notes'] : null;

        $member = $this->memberId
            ? $household->members()->findOrFail($this->memberId)
            : $household->members()->make();

        $member->fill($attributes)->save();
        $this->reset();

        return $member;
    }
}
