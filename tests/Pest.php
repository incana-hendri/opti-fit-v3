<?php

use App\Models\Household;
use App\Models\Membership;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests run against MariaDB (the `testing` database), never SQLite (docs/07).
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * A verified customer whose household has finished onboarding (one member, emergency contact).
 */
function customer(int $members = 1): User
{
    return User::factory()
        ->has(Household::factory()->onboarded($members), 'household')
        ->create();
}

function admin(): User
{
    return User::factory()->admin()->create();
}

/**
 * An active membership for the customer's household that started in the given period (default: this one),
 * covering all its active members.
 *
 * @param  array<string, mixed>  $attributes
 */
function activeMembership(User $user, ?string $startsPeriod = null, array $attributes = []): Membership
{
    return Membership::factory()
        ->for($user->household)
        ->for(Package::factory()->create(['sessions_per_week' => 2, 'credits_per_member' => 8]))
        ->active($startsPeriod)
        ->create($attributes);
}
