<?php

use App\Models\Household;
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
