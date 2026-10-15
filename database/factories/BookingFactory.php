<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * A charged `booked` booking. It only seeds the row: tests that need the credit rules go through `BookSession`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'member_id' => Member::factory(),
            'household_id' => fn (array $attributes) => Member::query()->findOrFail($attributes['member_id'])->household_id,
            'status' => BookingStatus::Booked,
            'credit_charged' => true,
            'late_cancellation' => false,
            'booked_by' => fn (array $attributes) => Member::query()->findOrFail($attributes['member_id'])->household->user_id,
        ];
    }

    public function attended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::Attended]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::NoShow]);
    }

    public function cancelled(bool $late = false): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
            'late_cancellation' => $late,
            'cancelled_at' => now(),
            'cancelled_by' => $attributes['booked_by'],
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => ['credit_charged' => false]);
    }

    public function bookedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['booked_by' => $user->id]);
    }
}
