<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fn (array $attributes) => User::find($attributes['user_id'])->last_name.' family',
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '083 '.fake()->numerify('### ####'),
        ];
    }

    /**
     * A household that has finished onboarding: it has one member to book.
     */
    public function onboarded(int $members = 1): static
    {
        return $this->has(Member::factory()->count($members));
    }

    public function withoutEmergencyContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
        ]);
    }
}
