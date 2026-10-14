<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-50 years', '-6 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'health_notes' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['archived_at' => now()]);
    }
}
