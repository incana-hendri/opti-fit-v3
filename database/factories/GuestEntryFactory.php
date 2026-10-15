<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\GuestEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestEntry>
 */
class GuestEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'name' => fake()->name().' (trial)',
            'participant_count' => 1,
            'note' => null,
            'created_by' => User::factory()->admin(),
        ];
    }
}
