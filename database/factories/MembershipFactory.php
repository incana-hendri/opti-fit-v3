<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Package;
use App\Support\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * A pending request for the household's active members. See the states for the other statuses.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory()->onboarded(),
            'package_id' => Package::factory(),
            'status' => MembershipStatus::Requested,
            'monthly_credits' => 8,
            'monthly_price_cents' => 52000,
            'requested_at' => now(),
        ];
    }

    // A membership with no members is never valid (BR-4.2), so it covers the household's active members by default.
    public function configure(): static
    {
        return $this->afterCreating(function (Membership $membership) {
            if ($membership->members()->doesntExist()) {
                $membership->members()->attach($membership->household->activeMembers()->pluck('id'));
            }
        });
    }

    /**
     * @param  string|null  $startsPeriod  defaults to the current period
     */
    public function active(?string $startsPeriod = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::Active,
            'starts_period' => $startsPeriod ?? Period::current(),
            'approved_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->active()->state(['status' => MembershipStatus::Paused]);
    }

    // BR-4.8: the customer cancelled; the last grant is this period's.
    public function ending(): static
    {
        return $this->active()->state(['ends_period' => Period::current()]);
    }

    public function cancelled(): static
    {
        return $this->active(Period::previous())->state([
            'status' => MembershipStatus::Cancelled,
            'ends_period' => Period::previous(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::Declined,
            'decline_reason' => 'Sorry, we are fully booked.',
        ]);
    }
}
