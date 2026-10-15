<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\ClassSession;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    /**
     * A scheduled class tomorrow at 05:00 (tests freeze time and set `starts_at` when it matters).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_template_id' => null,
            'location_id' => Location::factory(),
            'title' => 'Morning Functional Fitness',
            'starts_at' => today()->addDay()->setTime(5, 0),
            'duration_minutes' => 45,
            'capacity' => 15,
            'min_participants' => 3,
            'cutoff_hours' => null,
            'description' => null,
            'status' => SessionStatus::Scheduled,
        ];
    }

    public function at(string $startsAt): static
    {
        return $this->state(fn (array $attributes) => ['starts_at' => $startsAt]);
    }

    public function cancelled(string $reason = 'The coach is ill.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Cancelled,
            'cancellation_reason' => $reason,
        ]);
    }
}
