<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\ScheduleTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleTemplate>
 */
class ScheduleTemplateFactory extends Factory
{
    /**
     * A Monday 05:00 class, the studio's usual slot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Morning Functional Fitness',
            'location_id' => Location::factory(),
            'weekday' => 1,
            'start_time' => '05:00:00',
            'duration_minutes' => 45,
            'capacity' => 15,
            'min_participants' => 3,
            'cutoff_hours' => null,
            'description' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * @param  int  $weekday  ISO: 1 = Monday ... 7 = Sunday
     */
    public function on(int $weekday, string $startTime): static
    {
        return $this->state(fn (array $attributes) => ['weekday' => $weekday, 'start_time' => $startTime]);
    }
}
